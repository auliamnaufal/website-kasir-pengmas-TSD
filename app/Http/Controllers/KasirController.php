<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Piutang;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Milon\Barcode\DNS1D;

class KasirController extends Controller
{
    public function search(Request $request)
    {
        // Get the search query
        $searchTerm = $request->get('query');

        // Ensure that the search term is treated as a string for product_id comparison
        $products = Product::where('product_name', 'LIKE', '%' . $searchTerm . '%')
            ->orWhereRaw("CAST(product_id AS CHAR) LIKE ?", ['%' . $searchTerm . '%'])
            ->get();

        // Return the search result as JSON
        return response()->json($products);
    }


    public function prosesPembayaran(Request $request)
    {
        $request->validate([
            'payment_method' => 'required|string|in:Pembayaran Tunai,Pembayaran QRIS,Pembayaran Potong Gaji',
            'user_id' => 'nullable|integer|exists:users,user_id|required_if:payment_method,Pembayaran Potong Gaji',
            'products' => 'required|array',
            'products.*.id' => 'required|string',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.price' => 'required|integer',
        ]);

        // Get payment method and products from request
        $paymentMethod = match ($request->input('payment_method')) {
            'Pembayaran Tunai' => 'cash',
            'Pembayaran QRIS' => 'qris',
            'Pembayaran Potong Gaji' => 'potong_gaji',
        };

        $userId = $request->input('user_id');
        $products = $request->input('products');

        // Total is needed up front (before any writes) so a potong_gaji
        // transaction can be blocked without creating the order/payment.
        $totalAmount = 0;
        foreach ($products as $product) {
            $totalAmount += $product['quantity'] * $product['price'];
        }

        $period = null;
        $pegawai = null;

        if ($paymentMethod === 'potong_gaji') {
            $period = Piutang::periodFor(now());
            $pegawai = User::find($userId);

            if (Piutang::wouldExceedLimit($pegawai, $period, $totalAmount)) {
                throw ValidationException::withMessages([
                    'payment_method' => 'Transaksi ditolak: melebihi batas maksimum potong gaji pegawai ini untuk periode berjalan.',
                ]);
            }
        }

        // Process each product (e.g., save the order to the database)

        $order = Order::create([
            'order_date' => now(),
            'user_id' => $userId,
        ]);

        foreach ($products as $product) {
            // Find the product by product_id
            $idProduct = $product['id'];
            $quantityProduct = $product['quantity'];
            $priceProduct = $product['price'];

            $existingProduct = Product::where('product_id', $idProduct)->first();

            if ($existingProduct) {
                // Subtract the purchased quantity from the current stock
                $newStock = $existingProduct->quantity_in_stock - $quantityProduct;

                // Update the stock only if the new stock is not negative
                if ($newStock >= 0) {
                    $existingProduct->update(['quantity_in_stock' => $newStock]);
                } else {
                    // Handle the case where stock would go negative
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient stock for product: ' . $existingProduct->product_name
                    ], 400); // 400 Bad Request
                }
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock for product: ' . $existingProduct->product_name
                ], 400);
            }

            $orderdetail = OrderDetail::create([
                'order_id' => $order->order_id,
                'product_id' => $idProduct,
                'quantity' => $quantityProduct,
                'unit_price' => $priceProduct,
            ]);

            $orderDetails[] = [
                'product_name' => $existingProduct->product_name,
                'quantity' => $quantityProduct,
                'unit_price' => $priceProduct,
                'total_price' => $quantityProduct * $priceProduct,
            ];
        }

        $payment = Payment::create([
            'order_id' => $order->order_id,
            'payment_date' => now(),
            'payment_amount' => $totalAmount,
            'payment_method' => $paymentMethod,
        ]);

        if ($paymentMethod === 'potong_gaji') {
            Piutang::create([
                'user_id' => $userId,
                'source_type' => 'pembelian',
                'source_id' => $payment->payment_id,
                'amount' => $totalAmount,
                'status' => 'belum_lunas',
                'period' => $period,
            ]);
        }

        $receiptData = [
            'nama_toko' => config('kasir.nama_toko'),
            'situs' => config('kasir.situs'),
            'alamat' => config('kasir.alamat'),
            'order_id' => $order->order_id,
            'order_date' => \Illuminate\Support\Carbon::parse($order->order_date)->format('d/m/Y H:i'),
            'kasir' => $request->user()?->name ?? '-',
            'payment_method' => $paymentMethod,
            'metode' => config('kasir.label_metode.' . $paymentMethod, $paymentMethod),
            'total_amount' => $totalAmount,
            'order_details' => $orderDetails,
            'pegawai' => $pegawai?->name,
            'periode' => $period,
            'catatan' => config('kasir.catatan'),
        ];

        // Struk dirender di server supaya pratinjau di modal dan hasil cetak
        // memakai markup yang sama persis.
        $receiptData['barcode'] = $this->barcodeFor($order->order_id);
        $receiptData['html'] = view('contents.struk', $receiptData + ['items' => $orderDetails])->render();

        // Return a JSON response indicating success
        return response()->json([
            'success' => true,
            'receipt' => $receiptData,]);
    }

    /**
     * Barcode Code128 nomor transaksi. Null bila GD/barcode gagal, supaya
     * pencetakan struk tidak ikut berhenti.
     */
    private function barcodeFor(string $orderId): ?string
    {
        try {
            return (new DNS1D())->getBarcodePNG($orderId, 'C128', 2, 40, [0, 0, 0], true);
        } catch (\Throwable $e) {
            return null;
        }
    }
}

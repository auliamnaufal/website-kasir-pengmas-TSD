<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\JabatanConfig;
use App\Models\Piutang;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use charlieuki\ReceiptPrinter\ReceiptPrinter as ReceiptPrinter;

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

        $products = $request->input('products');

        // Total is needed up front (before any writes) so a potong_gaji
        // transaction can be blocked without creating the order/payment.
        $totalAmount = 0;
        foreach ($products as $product) {
            $totalAmount += $product['quantity'] * $product['price'];
        }

        $period = null;

        if ($paymentMethod === 'potong_gaji') {
            $period = Piutang::periodFor(now());
            $pegawai = User::find($request->input('user_id'));
            $jabatanConfig = JabatanConfig::where('jabatan', $pegawai->jabatan)->first();

            // ponytail: accounts predating this feature can have null gaji/jabatan
            // (spec #3 story 21 — old accounts keep working). There's no limit to
            // enforce without both, so such accounts are not blocked here.
            if ($pegawai->gaji !== null && $jabatanConfig !== null) {
                $existingPiutang = Piutang::where('user_id', $pegawai->user_id)
                    ->where('status', 'belum_lunas')
                    ->where('period', $period)
                    ->sum('amount');

                $limit = $pegawai->gaji * $jabatanConfig->max_potongan_persen / 100;

                if (($existingPiutang + $totalAmount) > $limit) {
                    throw ValidationException::withMessages([
                        'payment_method' => 'Transaksi ditolak: melebihi batas maksimum potong gaji pegawai ini untuk periode berjalan.',
                    ]);
                }
            }
        }

        // Process each product (e.g., save the order to the database)

        $order = Order::create([
            'order_date' => now(),
            'user_id' => $request->input('user_id'),
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
                'user_id' => $request->input('user_id'),
                'source_type' => 'pembelian',
                'source_id' => $payment->payment_id,
                'amount' => $totalAmount,
                'status' => 'belum_lunas',
                'period' => $period,
            ]);
        }

        $receiptData = [
            'store_name' => 'My Store Name',
            'store_address' => '123 Example St, City, Country',
            'order_id' => $order->order_id,
            'order_date' => $order->order_date,
            'payment_method' => $paymentMethod,
            'total_amount' => $totalAmount,
            'order_details' => $orderDetails,
        ];

        // Return a JSON response indicating success
        return response()->json([
            'success' => true,
            'receipt' => $receiptData,]);
    }
}

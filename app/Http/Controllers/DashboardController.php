<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        // Data Laporan Harian
        $totalOmset = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->whereDate('orders.order_date', $today)
            ->sum('payments.payment_amount');

        $jumlahTransaksi = Order::whereDate('order_date', $today)->count();

        $riwayatTransaksi = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->whereDate('orders.order_date', $today)
            ->selectRaw('TIME(orders.order_date) as time, payments.payment_amount, payments.payment_method, payments.order_id')
            ->orderBy('orders.order_date')
            ->get();

        $totalKeuntungan = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.product_id')
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
            ->whereDate('orders.order_date', $today)
            ->selectRaw('SUM((products.harga_jual - products.harga_beli) * order_details.quantity) AS total_keuntungan')
            ->value('total_keuntungan') ?? 0;

        $jumlahTransaksiYesterday = Order::whereDate('order_date', $yesterday)->count();

        $totalKeuntunganYesterday = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.product_id')
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
            ->whereDate('orders.order_date', $yesterday)
            ->selectRaw('SUM((products.harga_jual - products.harga_beli) * order_details.quantity) AS total_keuntungan')
            ->value('total_keuntungan') ?? 0;

        $totalOmsetYesterday = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->whereDate('orders.order_date', $yesterday)
            ->sum('payments.payment_amount');
        $omsetChange = $this->calculatePercentageChange($totalOmsetYesterday, $totalOmset);
        $keuntunganChange = $this->calculatePercentageChange($totalKeuntunganYesterday, $totalKeuntungan);
        $transaksiChange = $this->calculatePercentageChange($jumlahTransaksiYesterday, $jumlahTransaksi);
        // Data Produk
        $produkTerlaris = Product::join('order_details', 'products.product_id', '=', 'order_details.product_id')
            ->select('products.product_name', DB::raw('SUM(order_details.quantity) as total_terjual'))
            ->groupBy('products.product_name')
            ->orderBy('total_terjual', 'desc')
            ->limit(10)
            ->get();

        $produkHampirHabis = Product::where('quantity_in_stock', '<=', 10)
            ->orderBy('quantity_in_stock', 'asc')
            ->get();

        return view('contents.dashboard', [
            'omsetChange' => $omsetChange,
            'keuntunganChange' => $keuntunganChange,
            'transaksiChange' => $transaksiChange,
            'totalOmset' => $totalOmset,
            'jumlahTransaksi' => $jumlahTransaksi,
            'riwayatTransaksi' => $riwayatTransaksi,
            'totalKeuntungan' => $totalKeuntungan,
            'produkTerlaris' => $produkTerlaris,
            'produkHampirHabis' => $produkHampirHabis
        ]);
    }
    private function calculatePercentageChange($previous, $current)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0; // 100% jika sebelumnya 0 dan sekarang ada nilai
        }
        return round((($current - $previous) / $previous) * 100, 2);
    }

    public function getProdukTerlaris(Request $request)
    {
        $produkFilter = $request->input('produk_filter', 'all');

        // Menentukan tanggal berdasarkan filter
        $dateFilter = null;
        if ($produkFilter === '7_days') {
            $dateFilter = Carbon::now()->subDays(7);
        } elseif ($produkFilter === '30_days') {
            $dateFilter = Carbon::now()->subDays(30);
        }

        // Query untuk Produk Terlaris
        $produkTerlarisQuery = Product::join('order_details', 'products.product_id', '=', 'order_details.product_id')
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
            ->select('products.product_name', DB::raw('SUM(order_details.quantity) as total_terjual'));

        if ($dateFilter) {
            $produkTerlarisQuery->where('orders.order_date', '>=', $dateFilter);
        }

        $produkTerlaris = $produkTerlarisQuery
            ->groupBy('products.product_name')
            ->orderBy('total_terjual', 'desc')
            ->limit(10)
            ->get();

        // Siapkan data untuk dikirim ke frontend
        $response = [
            'product_names' => $produkTerlaris->pluck('product_name'),
            'total_terjual' => $produkTerlaris->pluck('total_terjual'),
        ];

        return response()->json($response);
    }

    public function monthlyReport()
    {
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $totalOmsetBulanIni = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->whereMonth('orders.order_date', $currentMonth)
            ->whereYear('orders.order_date', $currentYear)
            ->sum('payments.payment_amount');

        $jumlahTransaksiBulanIni = Order::whereMonth('order_date', $currentMonth)
            ->whereYear('order_date', $currentYear)
            ->count();

        $totalPendapatanBersih = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.product_id')
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
            ->whereMonth('orders.order_date', $currentMonth)
            ->whereYear('orders.order_date', $currentYear)
            ->selectRaw('SUM((products.harga_jual - products.harga_beli) * order_details.quantity) AS total_pendapatan_bersih')
            ->value('total_pendapatan_bersih') ?? 0;

        $trenTransaksiHarian = Order::whereMonth('order_date', $currentMonth)
            ->whereYear('order_date', $currentYear)
            ->selectRaw('DAY(order_date) as day, COUNT(*) as jumlah_transaksi')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $trenOmsetHarian = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->whereMonth('orders.order_date', $currentMonth)
            ->whereYear('orders.order_date', $currentYear)
            ->selectRaw('DAY(orders.order_date) as day, SUM(payments.payment_amount) as total_omset')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $trenKeuntunganHarian = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.product_id')
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
            ->whereMonth('orders.order_date', $currentMonth)
            ->whereYear('orders.order_date', $currentYear)
            ->selectRaw('DAY(orders.order_date) as day, SUM((products.harga_jual - products.harga_beli) * order_details.quantity) as total_keuntungan')
            ->groupBy('day')
            ->orderBy('day')
            ->get();

        $persentaseMetodePembayaran = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->whereMonth('orders.order_date', $currentMonth)
            ->whereYear('orders.order_date', $currentYear)
            ->select(
                'payments.payment_method',
                DB::raw('COUNT(*) as jumlah'),
                DB::raw('SUM(payments.payment_amount) as total_nominal'),
                DB::raw('ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM payments INNER JOIN orders ON payments.order_id = orders.order_id WHERE MONTH(orders.order_date) = ' . $currentMonth . ' AND YEAR(orders.order_date) = ' . $currentYear . '), 2) as persentase')
            )
            ->groupBy('payments.payment_method')
            ->get();
        
        $penjualanPerKategori = DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.product_id')
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
            ->join('categories', 'products.category_id', '=', 'categories.category_id') // Perbaikan di sini
            ->whereMonth('orders.order_date', $currentMonth)
            ->whereYear('orders.order_date', $currentYear)
            ->select(
                'categories.category_name',
                DB::raw('SUM(order_details.quantity) as total_penjualan'),
                DB::raw('SUM(order_details.quantity * products.harga_jual) as total_omset')
            )
            ->groupBy('categories.category_name')
            ->orderBy('total_omset', 'desc')
            ->get();
        
        // Menghitung rata-rata penjualan harian
        $totalHariAktif = $trenOmsetHarian->count(); // Jumlah hari dengan transaksi
        $rataRataPenjualanHarian = $totalHariAktif > 0 ? $totalOmsetBulanIni / $totalHariAktif : 0;

        $produkReport = DB::table('products')
        ->join('order_details', 'products.product_id', '=', 'order_details.product_id')
        ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
        ->join('categories', 'products.category_id', '=', 'categories.category_id')
        ->whereMonth('orders.order_date', $currentMonth)
        ->whereYear('orders.order_date', $currentYear)
        ->select(
            'products.product_name',
            'categories.category_name',
            'products.harga_beli',
            'products.harga_jual',
            DB::raw('SUM(order_details.quantity) as total_terjual'),
            DB::raw('SUM(order_details.quantity * products.harga_jual) as total_omset'),
            DB::raw('SUM((products.harga_jual - products.harga_beli) * order_details.quantity) as total_keuntungan')
        )
        ->groupBy('products.product_name', 'categories.category_name', 'products.harga_beli', 'products.harga_jual')
        ->orderBy('total_terjual', 'desc')
        ->get();


        

        return view('contents.monthly', [
            'totalOmsetBulanIni' => $totalOmsetBulanIni,
            'totalPendapatanBersih' => $totalPendapatanBersih,
            'jumlahTransaksiBulanIni' => $jumlahTransaksiBulanIni,
            'trenTransaksiHarian' => $trenTransaksiHarian,
            'trenOmsetHarian' => $trenOmsetHarian,
            'trenKeuntunganHarian' => $trenKeuntunganHarian,
            'persentaseMetodePembayaran' => $persentaseMetodePembayaran,
            'penjualanPerKategori' => $penjualanPerKategori,
            'rataRataPenjualanHarian' => $rataRataPenjualanHarian,
            'produkReport' => $produkReport,
        ]);
    }

}

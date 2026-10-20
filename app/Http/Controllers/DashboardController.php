<?php

namespace App\Http\Controllers;

use App\Services\LaporanService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private LaporanService $laporan)
    {
    }

    public function index()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $hariIni = $this->laporan->harian($today);
        $kemarin = $this->laporan->harian($yesterday);

        return view('contents.dashboard', [
            'omsetChange' => $this->calculatePercentageChange($kemarin['omset'], $hariIni['omset']),
            'keuntunganChange' => $this->calculatePercentageChange($kemarin['keuntungan'], $hariIni['keuntungan']),
            'transaksiChange' => $this->calculatePercentageChange($kemarin['transaksi'], $hariIni['transaksi']),
            'totalOmset' => $hariIni['omset'],
            'jumlahTransaksi' => $hariIni['transaksi'],
            'riwayatTransaksi' => $this->laporan->riwayatTransaksi($today),
            'totalKeuntungan' => $hariIni['keuntungan'],
            'produkTerlaris' => $this->laporan->produkTerlaris(),
            'produkHampirHabis' => $this->laporan->produkHampirHabis(),
            'omsetPerUnit' => $this->laporan->omsetPerUnit($today, $today),
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

        $produkTerlaris = $this->laporan->produkTerlaris($dateFilter);

        // Siapkan data untuk dikirim ke frontend
        return response()->json([
            'product_names' => $produkTerlaris->pluck('product_name'),
            'total_terjual' => $produkTerlaris->pluck('total_terjual'),
        ]);
    }

    public function monthlyReport()
    {
        $now = Carbon::now();

        return view('contents.monthly', $this->laporan->bulanan($now->month, $now->year) + [
            'omsetPerUnit' => $this->laporan->omsetPerUnit($now->copy()->startOfMonth(), $now->copy()->endOfMonth()),
        ]);
    }
}

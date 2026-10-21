<?php

namespace App\Services;

use App\Models\Product;
use App\Services\Laporan\UnitLaporan;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Semua query laporan/dashboard. Dipindah dari DashboardController dan ditulis
 * ulang tanpa fungsi khusus MySQL (TIME(), DAY(), MONTH()/YEAR() mentah) supaya
 * jalan di MySQL maupun SQLite. Hasil dan bentuk datanya sama dengan versi lama
 * (termasuk keuntungan yang masih dihitung dari harga produk saat ini).
 */
class LaporanService
{
    /** @param iterable<UnitLaporan> $units */
    public function __construct(private iterable $units = [])
    {
    }

    /** Omset tiap unit usaha untuk rentang tanggal inklusif. */
    public function omsetPerUnit(CarbonInterface $dari, CarbonInterface $sampai): array
    {
        $hasil = [];
        foreach ($this->units as $unit) {
            $hasil[] = ['nama' => $unit->nama(), 'omset' => $unit->omset($dari, $sampai)];
        }

        return $hasil;
    }

    /** Ringkasan satu hari: omset, jumlah transaksi, keuntungan. */
    public function harian(CarbonInterface $tanggal): array
    {
        return [
            'omset' => $this->omsetPos(fn ($q) => $q->whereDate('orders.order_date', $tanggal)),
            'transaksi' => DB::table('orders')->whereDate('order_date', $tanggal)->count(),
            'keuntungan' => $this->keuntungan(fn ($q) => $q->whereDate('orders.order_date', $tanggal)),
        ];
    }

    public function riwayatTransaksi(CarbonInterface $tanggal): Collection
    {
        return DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->whereDate('orders.order_date', $tanggal)
            ->select('orders.order_date as time', 'payments.payment_amount', 'payments.payment_method', 'payments.order_id')
            ->orderBy('orders.order_date')
            ->get();
    }

    public function produkTerlaris(?CarbonInterface $sejak = null, int $limit = 10): Collection
    {
        $query = Product::join('order_details', 'products.product_id', '=', 'order_details.product_id')
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id')
            ->select('products.product_name', DB::raw('SUM(order_details.quantity) as total_terjual'));

        if ($sejak) {
            $query->where('orders.order_date', '>=', $sejak);
        }

        return $query->groupBy('products.product_name')
            ->orderBy('total_terjual', 'desc')
            ->limit($limit)
            ->get();
    }

    public function produkHampirHabis(int $batas = 10): Collection
    {
        return Product::where('quantity_in_stock', '<=', $batas)
            ->orderBy('quantity_in_stock', 'asc')
            ->get();
    }

    /** Data laporan bulanan toko. */
    public function bulanan(int $bulan, int $tahun): array
    {
        $bulanIni = fn ($q) => $q->whereMonth('orders.order_date', $bulan)->whereYear('orders.order_date', $tahun);

        $omset = $this->omsetPos($bulanIni);

        $trenOmsetHarian = $this->perHari(
            DB::table('payments')
                ->join('orders', 'payments.order_id', '=', 'orders.order_id')
                ->tap($bulanIni)
                ->selectRaw('DATE(orders.order_date) as tanggal, SUM(payments.payment_amount) as total_omset')
                ->groupBy('tanggal')
                ->get()
        );

        return [
            'totalOmsetBulanIni' => $omset,
            'jumlahTransaksiBulanIni' => DB::table('orders')
                ->whereMonth('order_date', $bulan)->whereYear('order_date', $tahun)->count(),
            'totalPendapatanBersih' => $this->keuntungan($bulanIni),
            'trenTransaksiHarian' => $this->perHari(
                DB::table('orders')
                    ->whereMonth('order_date', $bulan)->whereYear('order_date', $tahun)
                    ->selectRaw('DATE(order_date) as tanggal, COUNT(*) as jumlah_transaksi')
                    ->groupBy('tanggal')
                    ->get()
            ),
            'trenOmsetHarian' => $trenOmsetHarian,
            'trenKeuntunganHarian' => $this->perHari(
                $this->queryItemProduk()
                    ->tap($bulanIni)
                    ->selectRaw('DATE(orders.order_date) as tanggal, SUM((products.harga_jual - products.harga_beli) * order_details.quantity) as total_keuntungan')
                    ->groupBy('tanggal')
                    ->get()
            ),
            'persentaseMetodePembayaran' => $this->persentaseMetodePembayaran($bulanIni),
            'penjualanPerKategori' => $this->queryItemProduk()
                ->join('categories', 'products.category_id', '=', 'categories.category_id')
                ->tap($bulanIni)
                ->select(
                    'categories.category_name',
                    DB::raw('SUM(order_details.quantity) as total_penjualan'),
                    DB::raw('SUM(order_details.quantity * products.harga_jual) as total_omset')
                )
                ->groupBy('categories.category_name')
                ->orderBy('total_omset', 'desc')
                ->get(),
            // Rata-rata per hari yang ada transaksinya.
            'rataRataPenjualanHarian' => $trenOmsetHarian->count() > 0 ? $omset / $trenOmsetHarian->count() : 0,
            'produkReport' => $this->queryItemProduk()
                ->join('categories', 'products.category_id', '=', 'categories.category_id')
                ->tap($bulanIni)
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
                ->get(),
        ];
    }

    /** Jumlah, nominal, dan persentase transaksi per metode bayar. */
    private function persentaseMetodePembayaran(callable $filter): Collection
    {
        $baris = DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->tap($filter)
            ->select('payments.payment_method', DB::raw('COUNT(*) as jumlah'), DB::raw('SUM(payments.payment_amount) as total_nominal'))
            ->groupBy('payments.payment_method')
            ->get();

        $total = $baris->sum('jumlah');

        return $baris->map(function ($row) use ($total) {
            $row->persentase = $total > 0 ? round($row->jumlah * 100 / $total, 2) : 0;

            return $row;
        });
    }

    private function omsetPos(callable $filter): float
    {
        return (float) DB::table('payments')
            ->join('orders', 'payments.order_id', '=', 'orders.order_id')
            ->tap($filter)
            ->sum('payments.payment_amount');
    }

    private function keuntungan(callable $filter): float
    {
        return (float) ($this->queryItemProduk()
            ->tap($filter)
            ->selectRaw('SUM((products.harga_jual - products.harga_beli) * order_details.quantity) AS total')
            ->value('total') ?? 0);
    }

    /** order_details + products + orders, dasar semua laporan per item. */
    private function queryItemProduk()
    {
        return DB::table('order_details')
            ->join('products', 'order_details.product_id', '=', 'products.product_id')
            ->join('orders', 'order_details.order_id', '=', 'orders.order_id');
    }

    /** Ubah kolom `tanggal` (Y-m-d) menjadi `day` (1-31) dan urutkan per hari. */
    private function perHari(Collection $baris): Collection
    {
        return $baris
            ->map(function ($row) {
                $row->day = Carbon::parse($row->tanggal)->day;

                return $row;
            })
            ->sortBy('day')
            ->values();
    }
}

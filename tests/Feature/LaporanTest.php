<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Order;
use App\Models\OrderDetail;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\Laporan\UnitLaporan;
use App\Services\LaporanService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private Product $produk;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-15 12:00:00');

        $kategori = Category::create(['category_name' => 'Sembako']);
        // harga beli 9.000, harga jual 10.000 -> untung 1.000 per item
        $this->produk = Product::create([
            'product_name' => 'Beras 1kg',
            'category_id' => $kategori->category_id,
            'quantity_in_stock' => 100,
            'harga_beli' => 9000,
            'harga_jual' => 10000,
        ]);
    }

    private function buatTransaksi(string $waktu, int $qty, string $metode = 'cash'): Order
    {
        $order = Order::create(['order_date' => $waktu]);
        OrderDetail::create([
            'order_id' => $order->order_id,
            'product_id' => $this->produk->product_id,
            'quantity' => $qty,
            'unit_price' => 10000,
        ]);
        Payment::create([
            'order_id' => $order->order_id,
            'payment_date' => $waktu,
            'payment_amount' => 10000 * $qty,
            'payment_method' => $metode,
        ]);

        return $order;
    }

    private function laporan(): LaporanService
    {
        return app(LaporanService::class);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_harian_menghitung_omset_transaksi_dan_keuntungan_hari_itu_saja(): void
    {
        $this->buatTransaksi('2026-03-15 09:00:00', 2);
        $this->buatTransaksi('2026-03-15 10:30:00', 1);
        $this->buatTransaksi('2026-03-14 10:00:00', 5); // kemarin, tidak ikut

        $hari = $this->laporan()->harian(Carbon::parse('2026-03-15'));

        $this->assertSame(30000.0, $hari['omset']);
        $this->assertSame(2, $hari['transaksi']);
        $this->assertSame(3000.0, $hari['keuntungan']);
    }

    public function test_harian_tanpa_data_bernilai_nol(): void
    {
        $hari = $this->laporan()->harian(Carbon::parse('2026-03-15'));

        $this->assertSame(['omset' => 0.0, 'transaksi' => 0, 'keuntungan' => 0.0], $hari);
    }

    public function test_riwayat_transaksi_berurutan_menurut_waktu(): void
    {
        $this->buatTransaksi('2026-03-15 11:00:00', 1, 'qris');
        $this->buatTransaksi('2026-03-15 08:00:00', 1);

        $riwayat = $this->laporan()->riwayatTransaksi(Carbon::parse('2026-03-15'));

        $this->assertCount(2, $riwayat);
        $this->assertSame('cash', $riwayat[0]->payment_method);
        $this->assertSame('08:00:00', Carbon::parse($riwayat[0]->time)->format('H:i:s'));
        $this->assertSame('qris', $riwayat[1]->payment_method);
    }

    public function test_bulanan_tren_harian_dan_ringkasan(): void
    {
        $this->buatTransaksi('2026-03-03 09:00:00', 1);
        $this->buatTransaksi('2026-03-03 15:00:00', 2, 'qris');
        $this->buatTransaksi('2026-03-10 09:00:00', 3);
        $this->buatTransaksi('2026-02-28 09:00:00', 9); // bulan lain, tidak ikut

        $data = $this->laporan()->bulanan(3, 2026);

        $this->assertSame(60000.0, $data['totalOmsetBulanIni']);
        $this->assertSame(3, $data['jumlahTransaksiBulanIni']);
        $this->assertSame(6000.0, $data['totalPendapatanBersih']);
        $this->assertSame([3, 10], $data['trenOmsetHarian']->pluck('day')->all());
        $this->assertEquals([30000, 30000], $data['trenOmsetHarian']->pluck('total_omset')->all());
        $this->assertEquals([2, 1], $data['trenTransaksiHarian']->pluck('jumlah_transaksi')->all());
        $this->assertEquals([3000, 3000], $data['trenKeuntunganHarian']->pluck('total_keuntungan')->all());
        $this->assertSame(30000.0, $data['rataRataPenjualanHarian']); // 60.000 / 2 hari aktif
    }

    public function test_bulanan_persentase_metode_pembayaran(): void
    {
        $this->buatTransaksi('2026-03-03 09:00:00', 1);
        $this->buatTransaksi('2026-03-04 09:00:00', 1);
        $this->buatTransaksi('2026-03-05 09:00:00', 1, 'qris');

        $metode = $this->laporan()->bulanan(3, 2026)['persentaseMetodePembayaran']->keyBy('payment_method');

        $this->assertSame(66.67, (float) $metode['cash']->persentase);
        $this->assertSame(33.33, (float) $metode['qris']->persentase);
        $this->assertEquals(20000, $metode['cash']->total_nominal);
    }

    public function test_bulanan_penjualan_per_kategori_dan_produk(): void
    {
        $this->buatTransaksi('2026-03-03 09:00:00', 4);

        $data = $this->laporan()->bulanan(3, 2026);

        $this->assertSame('Sembako', $data['penjualanPerKategori'][0]->category_name);
        $this->assertEquals(40000, $data['penjualanPerKategori'][0]->total_omset);
        $this->assertSame('Beras 1kg', $data['produkReport'][0]->product_name);
        $this->assertEquals(4, $data['produkReport'][0]->total_terjual);
    }

    public function test_bulanan_kosong_tidak_error(): void
    {
        $data = $this->laporan()->bulanan(3, 2026);

        $this->assertSame(0.0, $data['totalOmsetBulanIni']);
        $this->assertSame(0, $data['rataRataPenjualanHarian']);
        $this->assertCount(0, $data['persentaseMetodePembayaran']);
    }

    public function test_produk_terlaris_dengan_dan_tanpa_filter_tanggal(): void
    {
        $this->buatTransaksi('2026-03-14 09:00:00', 2); // 1 hari lalu
        $this->buatTransaksi('2026-01-01 09:00:00', 5); // lama

        $semua = $this->laporan()->produkTerlaris();
        $tujuhHari = $this->laporan()->produkTerlaris(Carbon::now()->subDays(7));

        $this->assertEquals(7, $semua[0]->total_terjual);
        $this->assertEquals(2, $tujuhHari[0]->total_terjual);
    }

    public function test_omset_per_unit_memuat_toko_dan_rentang_inklusif(): void
    {
        $this->buatTransaksi('2026-03-01 00:00:00', 1);
        $this->buatTransaksi('2026-03-31 23:30:00', 1); // hari terakhir tetap ikut
        $this->buatTransaksi('2026-04-01 00:00:00', 1); // di luar rentang

        $unit = $this->laporan()->omsetPerUnit(Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31'));

        $this->assertSame([['nama' => 'Toko (Kasir)', 'omset' => 20000.0]], $unit);
    }

    public function test_unit_baru_cukup_mengimplementasikan_interface(): void
    {
        $unitBaru = new class implements UnitLaporan {
            public function nama(): string
            {
                return 'Unit Uji';
            }

            public function omset(CarbonInterface $dari, CarbonInterface $sampai): float
            {
                return 1234.0;
            }
        };

        $unit = (new LaporanService([$unitBaru]))->omsetPerUnit(Carbon::now(), Carbon::now());

        $this->assertSame([['nama' => 'Unit Uji', 'omset' => 1234.0]], $unit);
    }

    public function test_halaman_dashboard_admin_tampil_dengan_omset_per_unit(): void
    {
        $this->buatTransaksi('2026-03-15 09:00:00', 2);

        $this->actingAs($this->admin())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Omset Hari Ini per Unit Usaha')
            ->assertSee('Toko (Kasir)')
            ->assertSee('Rp20.000');
    }

    public function test_halaman_laporan_bulanan_admin_tampil_dengan_omset_per_unit(): void
    {
        $this->buatTransaksi('2026-03-03 09:00:00', 2);

        $this->actingAs($this->admin())
            ->get(route('admin.monthlyreport'))
            ->assertOk()
            ->assertSee('Omset Bulan Ini per Unit Usaha')
            ->assertSee('Rp20.000');
    }

    public function test_endpoint_produk_terlaris_mengembalikan_json(): void
    {
        $this->buatTransaksi('2026-03-14 09:00:00', 3);

        $this->actingAs($this->admin())
            ->getJson(route('dashboard.produkTerlaris', ['produk_filter' => '7_days']))
            ->assertOk()
            ->assertJson(['product_names' => ['Beras 1kg'], 'total_terjual' => [3]]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\JabatanConfig;
use App\Models\Pinjaman;
use App\Models\Piutang;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\PinjamanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SimpanPinjamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-10 09:00:00');
        JabatanConfig::create(['jabatan' => 'Staff', 'level' => 1, 'simpanan_wajib' => 100000, 'max_potongan_persen' => 50]);
        JabatanConfig::create(['jabatan' => 'Kabag', 'level' => 3, 'simpanan_wajib' => 200000, 'max_potongan_persen' => 50]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Anggota',
            'email' => 'anggota'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
            'jabatan' => 'Staff',
            'gaji' => 10000000, // batas potong gaji 5 jt / periode
        ], $overrides));
    }

    private function admin(): User
    {
        return $this->user(['role' => 'admin', 'jabatan' => null, 'gaji' => null]);
    }

    private function service(): PinjamanService
    {
        return app(PinjamanService::class);
    }

    private function ajukan(User $anggota, float $pokok = 10000000, int $tenor = 10, string $metode = 'bayar_sendiri', bool $kompensasi = false): Pinjaman
    {
        return $this->service()->ajukan($anggota, $pokok, $tenor, $metode, now(), $kompensasi);
    }

    public function test_pinjaman_disetujui_menyimpan_jadwal_dan_memecah_administrasi(): void
    {
        $anggota = $this->user();

        $pinjaman = $this->ajukan($anggota);

        $this->assertSame('aktif', $pinjaman->status);
        $this->assertCount(10, $pinjaman->angsuran);
        $this->assertEquals(300000, $pinjaman->administrasi);
        $this->assertEquals(100000, $pinjaman->administrasi_pendapatan);
        $this->assertEquals(200000, $pinjaman->administrasi_sukarela);
        $this->assertEquals(9700000, $pinjaman->dana_diterima);
        $this->assertEquals(10, $pinjaman->bulan_bunga_minimum);

        $pertama = $pinjaman->angsuran->first();
        $this->assertSame('2026-02-10', $pertama->jatuh_tempo->toDateString());
        $this->assertEquals(1000000, $pertama->pokok);
        $this->assertEquals(100000, $pertama->bunga);

        $this->assertDatabaseHas('simpanan', [
            'user_id' => $anggota->user_id,
            'jenis' => 'sukarela',
            'jumlah' => 200000,
            'sumber_type' => 'pinjaman',
            'sumber_id' => $pinjaman->id,
        ]);
        // Bayar sendiri tidak membuat piutang potong gaji.
        $this->assertDatabaseCount('piutang', 0);
    }

    public function test_potong_gaji_mencatat_piutang_per_angsuran_di_periode_jatuh_tempo(): void
    {
        $anggota = $this->user();

        $pinjaman = $this->ajukan($anggota, metode: 'potong_gaji');

        $this->assertSame('aktif', $pinjaman->status);
        $this->assertSame(10, Piutang::where('source_type', 'pinjaman')->count());
        $pertama = $pinjaman->angsuran->first();
        $this->assertDatabaseHas('piutang', [
            'user_id' => $anggota->user_id,
            'source_type' => 'pinjaman',
            'source_id' => $pertama->id,
            'amount' => 1100000,
            'status' => 'belum_lunas',
            'period' => Piutang::periodFor($pertama->jatuh_tempo), // 10 Feb -> 2026-02
        ]);
    }

    public function test_potong_gaji_ditolak_bila_gaji_belum_dikonfigurasi(): void
    {
        $anggota = $this->user(['gaji' => null]);

        $pinjaman = $this->ajukan($anggota, metode: 'potong_gaji');

        $this->assertSame('ditolak', $pinjaman->status);
        $this->assertStringContainsString('belum dikonfigurasi', $pinjaman->alasan_penolakan);
        $this->assertCount(0, $pinjaman->angsuran()->get());
        $this->assertDatabaseCount('simpanan', 0);
    }

    public function test_potong_gaji_ditolak_bila_angsuran_melebihi_batas_potongan(): void
    {
        $anggota = $this->user(['gaji' => 2000000]); // batas 1 jt, angsuran 1,1 jt

        $pinjaman = $this->ajukan($anggota, metode: 'potong_gaji');

        $this->assertSame('ditolak', $pinjaman->status);
        $this->assertStringContainsString('melebihi batas', $pinjaman->alasan_penolakan);
        $this->assertDatabaseCount('piutang', 0);
    }

    public function test_plafon_outstanding_menolak_otomatis_di_atas_50_juta(): void
    {
        $anggota = $this->user();
        $this->ajukan($anggota, 45000000, 60);

        $ditolak = $this->ajukan($anggota, 6000000, 10);
        $disetujui = $this->ajukan($anggota, 5000000, 10);

        $this->assertSame('ditolak', $ditolak->status);
        $this->assertStringContainsString('plafon', $ditolak->alasan_penolakan);
        $this->assertSame('aktif', $disetujui->status);
        $this->assertEquals(0, $this->service()->sisaPlafon($anggota, now()));
    }

    public function test_plafon_outstanding_bertambah_lagi_setelah_angsuran_dibayar(): void
    {
        $anggota = $this->user();
        $pinjaman = $this->ajukan($anggota, 50000000, 10);

        $this->service()->bayarAngsuran($pinjaman->angsuran->first());

        $this->assertEquals(5000000, $this->service()->sisaPlafon($anggota, now()));
    }

    public function test_plafon_tahunan_menghitung_pokok_pinjaman_di_tahun_kalender(): void
    {
        config(['simpan_pinjam.plafon_basis' => 'tahunan']);
        $anggota = $this->user();
        $pinjaman = $this->ajukan($anggota, 50000000, 10);
        $this->service()->lunasiDipercepat($pinjaman);

        // Pinjaman yang sudah lunas tetap memakai plafon tahun itu.
        $this->assertSame('ditolak', $this->ajukan($anggota, 1000000, 10)->status);

        Carbon::setTestNow('2027-01-05');
        $this->assertSame('aktif', $this->ajukan($anggota, 1000000, 10)->status);
    }

    public function test_tenor_di_luar_rentang_ditolak(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->ajukan($this->user(), 1000000, 61);
    }

    public function test_angsuran_harus_dibayar_urut_dan_pinjaman_lunas_di_angsuran_terakhir(): void
    {
        $pinjaman = $this->ajukan($this->user());

        try {
            $this->service()->bayarAngsuran($pinjaman->angsuran[1]);
            $this->fail('Angsuran ke-2 seharusnya ditolak sebelum ke-1 dibayar.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ke-1', $e->errors()['angsuran'][0]);
        }

        foreach ($pinjaman->angsuran as $angsuran) {
            $pinjaman = $this->service()->bayarAngsuran($angsuran);
        }

        $this->assertSame('lunas', $pinjaman->status);
        $this->assertEquals(10000000, $pinjaman->pelunasan_pokok);
        $this->assertEquals(1000000, $pinjaman->pelunasan_bunga);
        $this->assertNotNull($pinjaman->dilunasi_pada);
    }

    public function test_bayar_angsuran_potong_gaji_ikut_melunasi_piutangnya(): void
    {
        $pinjaman = $this->ajukan($this->user(), metode: 'potong_gaji');
        $pertama = $pinjaman->angsuran->first();

        $this->service()->bayarAngsuran($pertama);

        $this->assertDatabaseHas('piutang', ['source_type' => 'pinjaman', 'source_id' => $pertama->id, 'status' => 'lunas']);
        $this->assertSame(9, Piutang::where('source_type', 'pinjaman')->where('status', 'belum_lunas')->count());
    }

    public function test_tandai_lunas_piutang_pinjaman_ikut_membayar_angsuran(): void
    {
        $admin = $this->admin();
        $pinjaman = $this->ajukan($this->user(), metode: 'potong_gaji');
        $piutangKe1 = Piutang::where('source_id', $pinjaman->angsuran[0]->id)->where('source_type', 'pinjaman')->first();
        $piutangKe2 = Piutang::where('source_id', $pinjaman->angsuran[1]->id)->where('source_type', 'pinjaman')->first();

        // Urutan angsuran tetap dijaga lewat jalur piutang.
        $this->actingAs($admin)->put(route('piutang.tandai-lunas', $piutangKe2))->assertSessionHasErrors('angsuran');

        $this->actingAs($admin)->put(route('piutang.tandai-lunas', $piutangKe1))->assertRedirect();

        $this->assertSame('lunas', $pinjaman->angsuran[0]->fresh()->status);
        $this->assertSame($admin->user_id, $piutangKe1->fresh()->settled_by);
    }

    public function test_pelunasan_dipercepat_menagih_bunga_sampai_bulan_ke_10_dan_memisah_pokok_bunga(): void
    {
        $pinjaman = $this->ajukan($this->user(), 10000000, 20, 'potong_gaji');
        foreach ($pinjaman->angsuran->take(3) as $angsuran) {
            $this->service()->bayarAngsuran($angsuran);
        }

        $rincian = $this->service()->lunasiDipercepat($pinjaman);
        $pinjaman->refresh();

        $this->assertEquals(8500000, $rincian->sisaPokok);
        $this->assertEquals(700000, $rincian->bungaDitagih); // bulan 4..10
        $this->assertSame('lunas', $pinjaman->status);
        $this->assertEquals(10000000, $pinjaman->pelunasan_pokok);
        $this->assertEquals(1000000, $pinjaman->pelunasan_bunga); // 3 x 100rb + 700rb
        $this->assertSame(17, $pinjaman->angsuran()->where('status', 'pelunasan')->count());
        // Piutang potong gaji yang belum jatuh tempo tidak akan dipotong lagi.
        $this->assertSame(0, Piutang::where('source_type', 'pinjaman')->where('status', 'belum_lunas')->count());
    }

    public function test_kompensasi_melunasi_pinjaman_lama_dari_pinjaman_baru(): void
    {
        $anggota = $this->user();
        $lama = $this->ajukan($anggota, 30000000, 60, 'potong_gaji');
        $rincianLama = $this->service()->rincianPelunasan($lama); // 30 jt + bunga 10 bulan

        // Tanpa kompensasi plafon outstanding sudah terpakai 30 jt, jadi 50 jt pasti ditolak.
        $baru = $this->ajukan($anggota, 50000000, 60, 'bayar_sendiri', kompensasi: true);
        $lama->refresh();

        $this->assertSame('aktif', $baru->status, (string) $baru->alasan_penolakan);
        $this->assertEquals(30000000, $rincianLama->sisaPokok);
        $this->assertEquals(3000000, $rincianLama->bungaDitagih);
        $this->assertEquals(33000000, $baru->kompensasi);
        $this->assertEquals(50000000 - 1500000 - 33000000, $baru->dana_diterima);
        $this->assertSame('lunas', $lama->status);
        $this->assertSame($baru->id, $lama->dikompensasi_oleh);
        $this->assertSame(0, Piutang::where('source_type', 'pinjaman')->where('status', 'belum_lunas')->count());
    }

    public function test_kompensasi_ditolak_bila_pinjaman_baru_tidak_cukup_menutup_pinjaman_lama(): void
    {
        $anggota = $this->user();
        $lama = $this->ajukan($anggota, 20000000, 20);

        $baru = $this->ajukan($anggota, 5000000, 10, kompensasi: true);

        $this->assertSame('ditolak', $baru->status);
        $this->assertStringContainsString('kompensasi', $baru->alasan_penolakan);
        $this->assertSame('aktif', $lama->fresh()->status);
    }

    public function test_laporan_bulanan_memisah_pokok_bunga_dan_sisa_per_anggota(): void
    {
        $anggota = $this->user();
        $pinjaman = $this->ajukan($anggota, 10000000, 10);
        $this->service()->bayarAngsuran($pinjaman->angsuran->first());

        $laporan = $this->service()->laporanBulanan('2026-02');

        $this->assertEquals(1000000, $laporan['total_pokok']);
        $this->assertEquals(100000, $laporan['total_bunga']);
        $baris = $laporan['per_anggota']->first();
        $this->assertSame($anggota->user_id, $baris['anggota']->user_id);
        $this->assertEquals(9000000, $baris['sisa_pokok']);
        $this->assertEquals(900000, $baris['sisa_bunga']);
        $this->assertTrue($baris['sudah_dibayar']);
        $this->assertEquals(0, $laporan['pendapatan_administrasi']); // diajukan Januari

        $this->assertEquals(100000, $this->service()->laporanBulanan('2026-01')['pendapatan_administrasi']);

        // Setelah pelunasan dipercepat, angsuran bulan berikutnya tidak lagi jatuh tempo.
        $this->service()->lunasiDipercepat($pinjaman);
        $this->assertTrue($this->service()->laporanBulanan('2026-03')['per_anggota']->isEmpty());
        $this->assertCount(1, $this->service()->laporanBulanan('2026-02')['per_anggota']);
    }

    public function test_alur_http_admin_simulasi_ajukan_bayar_lunas_dan_invoice_pdf(): void
    {
        $admin = $this->admin();
        $anggota = $this->user(['name' => 'Budi Santoso']);
        $input = ['user_id' => $anggota->user_id, 'pokok' => 10000000, 'tenor' => 10, 'metode_bayar' => 'bayar_sendiri'];

        $this->actingAs($admin)->get(route('simpan-pinjam.index', $input))
            ->assertOk()
            ->assertSee('Ajukan Pinjaman')
            ->assertSee('Rp9.700.000');

        $this->actingAs($admin)->post(route('simpan-pinjam.store'), $input)->assertRedirect();
        $pinjaman = Pinjaman::firstOrFail();
        $this->assertSame($admin->user_id, $pinjaman->dibuat_oleh);

        $this->actingAs($admin)->get(route('simpan-pinjam.show', $pinjaman))->assertOk()->assertSee('Budi Santoso');
        $this->actingAs($admin)->get(route('simpan-pinjam.invoice', $pinjaman))
            ->assertRedirect(route('simpan-pinjam.show', $pinjaman));

        $this->actingAs($admin)->post(route('simpan-pinjam.bayar-angsuran', $pinjaman->angsuran->first()))->assertRedirect();
        $this->actingAs($admin)->post(route('simpan-pinjam.lunasi', $pinjaman))->assertRedirect();
        $this->assertSame('lunas', $pinjaman->fresh()->status);

        $pdf = $this->actingAs($admin)->get(route('simpan-pinjam.invoice', $pinjaman));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($admin)->get(route('simpan-pinjam.laporan', ['bulan' => '2026-01']))->assertOk()->assertSee('Budi Santoso');
    }

    public function test_store_menolak_tenor_di_luar_rentang(): void
    {
        $admin = $this->admin();
        $anggota = $this->user();

        $this->actingAs($admin)->post(route('simpan-pinjam.store'), [
            'user_id' => $anggota->user_id, 'pokok' => 1000000, 'tenor' => 9, 'metode_bayar' => 'bayar_sendiri',
        ])->assertSessionHasErrors('tenor');

        $this->assertDatabaseCount('pinjaman', 0);
    }

    public function test_non_admin_tidak_bisa_mengakses_simpan_pinjam(): void
    {
        $karyawan = $this->user();

        $this->actingAs($karyawan)->get(route('simpan-pinjam.index'))->assertRedirect();
        $this->actingAs($karyawan)->post(route('simpan-pinjam.store'), [
            'user_id' => $karyawan->user_id, 'pokok' => 1000000, 'tenor' => 10, 'metode_bayar' => 'bayar_sendiri',
        ])->assertRedirect();

        $this->assertDatabaseCount('pinjaman', 0);
    }
}

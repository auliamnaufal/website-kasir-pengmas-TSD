<?php

namespace Tests\Feature;

use App\Models\JabatanConfig;
use App\Models\KreditBarang;
use App\Models\Piutang;
use App\Models\User;
use App\Services\KreditService;
use App\Services\Laporan\KreditUnitLaporan;
use App\Services\Laporan\SimpanPinjamUnitLaporan;
use App\Services\PinjamanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Kredit Pertokoan (issue #2) di atas MesinAngsuran P2: jadwal "bunga sampai
 * bulan ke-11", administrasi, potong gaji via piutang, pembayaran berurutan,
 * pelunasan dipercepat, laporan, dan invoice. Plafon tahunan diuji di
 * KreditPlafonTest.
 */
class KreditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-10 09:00:00');
        JabatanConfig::create(['jabatan' => 'Staff', 'level' => 1, 'simpanan_wajib' => 100000, 'max_potongan_persen' => 50]);
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

    private function service(): KreditService
    {
        return app(KreditService::class);
    }

    private function ajukan(User $anggota, float $pokok = 3600000, int $tenor = 12, string $metode = 'bayar_sendiri'): KreditBarang
    {
        return $this->service()->ajukan($anggota, 'Kulkas', $pokok, $tenor, $metode, now());
    }

    // --- jadwal ----------------------------------------------------------------

    public function test_tenor_12_dikenai_bunga_sampai_bulan_ke_11_saja(): void
    {
        $kredit = $this->ajukan($this->user(), 3600000, 12);

        $this->assertSame('disetujui', $kredit->status);
        $this->assertCount(12, $kredit->angsuran);
        $this->assertEquals(11, $kredit->bunga_sampai_bulan);

        $bunga = $kredit->angsuran->pluck('bunga')->map(fn ($b) => (float) $b)->all();
        $this->assertSame(array_fill(0, 11, 36000.0), array_slice($bunga, 0, 11));
        $this->assertSame(0.0, $bunga[11], 'bulan ke-12 tidak berbunga');

        $this->assertEquals(3600000, $kredit->angsuran->sum('pokok'));
        $this->assertEquals(396000, $kredit->angsuran->sum('bunga'));
        $this->assertSame('2026-02-10', $kredit->angsuran->first()->jatuh_tempo->toDateString());
        $this->assertSame('2027-01-10', $kredit->angsuran->last()->jatuh_tempo->toDateString());
    }

    public function test_tenor_10_berbunga_sepanjang_tenor(): void
    {
        $kredit = $this->ajukan($this->user(), 1000000, 10);

        $this->assertCount(10, $kredit->angsuran);
        $this->assertEquals(1000000, $kredit->angsuran->sum('pokok'));
        $this->assertEquals(100000, $kredit->angsuran->sum('bunga'), '10 bulan x 1% x 1 jt');
    }

    public function test_administrasi_2_persen_dicatat_sekali_dan_tidak_masuk_angsuran(): void
    {
        $kredit = $this->ajukan($this->user(), 3600000, 12);

        $this->assertEquals(72000, $kredit->administrasi);
        $this->assertEquals(3600000 + 396000, $kredit->angsuran->sum('pokok') + $kredit->angsuran->sum('bunga'));
    }

    public function test_parameter_jadwal_dibekukan_di_kredit_meski_config_berubah(): void
    {
        $kredit = $this->ajukan($this->user(), 3600000, 12);

        config(['kredit.bunga_persen' => 5, 'kredit.bunga_sampai_bulan' => 3]);

        $jadwal = $this->service()->jadwalKredit($kredit->fresh());
        $this->assertEquals(396000, $jadwal->totalBunga());
    }

    public function test_simulasi_tidak_menyimpan_apa_pun_dan_sama_dengan_hasil_pengajuan(): void
    {
        $anggota = $this->user();

        $simulasi = $this->service()->simulasi($anggota, 3600000, 12, 'bayar_sendiri', now());
        $this->assertSame(0, KreditBarang::count());
        $this->assertNull($simulasi['alasan_penolakan']);
        $this->assertEquals(5000000, $simulasi['sisa_plafon']);

        $kredit = $this->ajukan($anggota, 3600000, 12);
        $this->assertEquals($simulasi['jadwal']->totalBunga(), $kredit->angsuran->sum('bunga'));
    }

    // --- penolakan otomatis ------------------------------------------------------

    public function test_melebihi_plafon_tahunan_ditolak_tanpa_jadwal(): void
    {
        $kredit = $this->ajukan($this->user(), 6000000, 12);

        $this->assertSame('ditolak', $kredit->status);
        $this->assertStringContainsString('plafon', $kredit->alasan_penolakan);
        $this->assertCount(0, $kredit->angsuran);
        $this->assertNull($kredit->tanggal_mulai);
    }

    public function test_potong_gaji_ditolak_bila_melebihi_batas_dan_tidak_memakai_plafon(): void
    {
        $anggota = $this->user(['gaji' => 500000]); // batas 250 rb/periode < angsuran 336 rb

        $ditolak = $this->ajukan($anggota, 3600000, 12, 'potong_gaji');

        $this->assertSame('ditolak', $ditolak->status);
        $this->assertStringContainsString('Potongan gaji periode 2026-02', $ditolak->alasan_penolakan);
        $this->assertCount(0, $ditolak->angsuran);
        $this->assertSame(0, Piutang::count());

        // Kredit ditolak tidak memakai plafon: pengajuan Rp5 jt berikutnya masih lolos.
        $this->assertSame('disetujui', $this->ajukan($anggota, 5000000, 12, 'bayar_sendiri')->status);
    }

    public function test_potong_gaji_ditolak_bila_gaji_atau_jabatan_belum_dikonfigurasi(): void
    {
        $tanpaGaji = $this->user(['gaji' => null]);
        $kredit = $this->ajukan($tanpaGaji, 1000000, 10, 'potong_gaji');

        $this->assertSame('ditolak', $kredit->status);
        $this->assertStringContainsString('belum dikonfigurasi', $kredit->alasan_penolakan);
    }

    public function test_potong_gaji_menghitung_piutang_lain_di_periode_yang_sama(): void
    {
        $anggota = $this->user(['gaji' => 1000000]); // batas 500 rb
        Piutang::create([
            'user_id' => $anggota->user_id, 'source_type' => 'pembelian', 'source_id' => 1,
            'amount' => 300000, 'status' => 'belum_lunas', 'period' => '2026-02',
        ]);

        // Angsuran 336 rb + piutang 300 rb = 636 rb > 500 rb.
        $kredit = $this->ajukan($anggota, 3600000, 12, 'potong_gaji');

        $this->assertSame('ditolak', $kredit->status);
    }

    public function test_tenor_dan_metode_bayar_tidak_valid_melempar_exception(): void
    {
        $anggota = $this->user();

        foreach ([[1000000, 9, 'bayar_sendiri'], [1000000, 13, 'bayar_sendiri'], [1000000, 12, 'tunai'], [0, 12, 'bayar_sendiri']] as [$pokok, $tenor, $metode]) {
            try {
                $this->ajukan($anggota, $pokok, $tenor, $metode);
                $this->fail("Seharusnya ditolak: {$pokok}/{$tenor}/{$metode}");
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertSame(0, KreditBarang::count());
    }

    // --- piutang potong gaji -----------------------------------------------------

    public function test_potong_gaji_menulis_satu_piutang_kredit_per_angsuran(): void
    {
        $kredit = $this->ajukan($this->user(), 3600000, 12, 'potong_gaji');

        $this->assertSame('disetujui', $kredit->status);
        $this->assertSame(12, Piutang::where('source_type', 'kredit')->count());

        $pertama = $kredit->angsuran->first();
        $this->assertDatabaseHas('piutang', [
            'user_id' => $kredit->user_id,
            'source_type' => 'kredit',
            'source_id' => $pertama->id,
            'amount' => 336000,
            'status' => 'belum_lunas',
            'period' => '2026-02', // jatuh tempo 10 Feb -> window yang ditutup 15 Feb
        ]);
        // Bulan ke-12 tanpa bunga: hanya pokok.
        $this->assertDatabaseHas('piutang', ['source_id' => $kredit->angsuran->last()->id, 'amount' => 300000]);
    }

    public function test_bayar_sendiri_tidak_menulis_piutang(): void
    {
        $this->ajukan($this->user(), 3600000, 12, 'bayar_sendiri');

        $this->assertSame(0, Piutang::count());
    }

    // --- pembayaran --------------------------------------------------------------

    public function test_bayar_angsuran_harus_berurutan_dan_menandai_piutang_lunas(): void
    {
        $oleh = $this->admin();
        $kredit = $this->ajukan($this->user(), 3600000, 12, 'potong_gaji');
        [$ke1, $ke2] = [$kredit->angsuran[0], $kredit->angsuran[1]];

        try {
            $this->service()->bayarAngsuran($ke2, $oleh);
            $this->fail('Angsuran ke-2 tidak boleh dibayar lebih dulu.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('ke-1', $e->errors()['angsuran'][0]);
        }

        $this->service()->bayarAngsuran($ke1, $oleh);

        $this->assertSame('lunas', $ke1->fresh()->status);
        $this->assertEquals($oleh->user_id, $ke1->fresh()->dibayar_oleh);
        $piutang = Piutang::where('source_type', 'kredit')->where('source_id', $ke1->id)->first();
        $this->assertSame('lunas', $piutang->status);
        $this->assertEquals($oleh->user_id, $piutang->settled_by);

        $this->expectException(ValidationException::class);
        $this->service()->bayarAngsuran($ke1, $oleh); // sudah dibayar
    }

    public function test_angsuran_terakhir_membuat_kredit_lunas_dengan_pokok_dan_bunga_terpisah(): void
    {
        $kredit = $this->ajukan($this->user(), 3600000, 12);

        foreach ($kredit->angsuran as $angsuran) {
            $kredit = $this->service()->bayarAngsuran($angsuran);
        }

        $this->assertSame('lunas', $kredit->status);
        $this->assertEquals(3600000, $kredit->pelunasan_pokok);
        $this->assertEquals(396000, $kredit->pelunasan_bunga);
        $this->assertNotNull($kredit->dilunasi_pada);
    }

    public function test_kredit_lunas_atau_ditolak_tidak_bisa_dibayar_lagi(): void
    {
        $this->expectException(ValidationException::class);

        $kredit = $this->ajukan($this->user(), 1000000, 10);
        $this->service()->lunasiDipercepat($kredit);
        $this->service()->bayarAngsuran($kredit->angsuran->first());
    }

    // --- pelunasan dipercepat ----------------------------------------------------

    public function test_pelunasan_dipercepat_menagih_bunga_minimum_dan_membebaskan_sisanya(): void
    {
        $kredit = $this->ajukan($this->user(), 3600000, 12, 'potong_gaji');
        $this->service()->bayarAngsuran($kredit->angsuran[0]);
        $this->service()->bayarAngsuran($kredit->angsuran[1]);

        $rincian = $this->service()->rincianPelunasan($kredit->fresh());

        $this->assertSame(2, $rincian->angsuranTerbayar);
        $this->assertSame(10, $rincian->bungaSampaiBulan, 'bunga ditagih sampai bulan ke-10 (minimum)');
        $this->assertEquals(3000000, $rincian->sisaPokok);
        $this->assertEquals(288000, $rincian->bungaDitagih, 'bulan 3..10 x 36 rb');
        $this->assertEquals(36000, $rincian->bungaDibebaskan, 'bunga bulan ke-11 dibebaskan');
        $this->assertEquals(3288000, $rincian->total);

        $this->service()->lunasiDipercepat($kredit->fresh());

        $kredit = $kredit->fresh('angsuran');
        $this->assertSame('lunas', $kredit->status);
        $this->assertEquals(3600000, $kredit->pelunasan_pokok);
        $this->assertEquals(72000 + 288000, $kredit->pelunasan_bunga);
        $this->assertSame(2, $kredit->angsuran->where('status', 'lunas')->count());
        $this->assertSame(10, $kredit->angsuran->where('status', 'pelunasan')->count());
        // Piutang yang belum jatuh tempo tidak lagi dipotong.
        $this->assertSame(0, Piutang::where('source_type', 'kredit')->where('status', 'belum_lunas')->count());
        $this->assertSame(2, Piutang::where('source_type', 'kredit')->where('status', 'lunas')->count());
    }

    public function test_pelunasan_lewat_bulan_minimum_hanya_menagih_bunga_bulan_berjalan(): void
    {
        $kredit = $this->ajukan($this->user(), 3600000, 12);
        foreach ($kredit->angsuran->take(10) as $angsuran) {
            $this->service()->bayarAngsuran($angsuran);
        }

        $rincian = $this->service()->rincianPelunasan($kredit->fresh());

        $this->assertSame(11, $rincian->bungaSampaiBulan);
        $this->assertEquals(600000, $rincian->sisaPokok);
        $this->assertEquals(36000, $rincian->bungaDitagih);
        $this->assertEquals(0, $rincian->bungaDibebaskan, 'bulan ke-12 memang tidak berbunga');
    }

    // --- plafon + lintas modul ---------------------------------------------------

    public function test_kredit_dan_pinjaman_simpan_pinjam_tidak_saling_memakai_plafon(): void
    {
        $anggota = $this->user();
        app(PinjamanService::class)->ajukan($anggota, 20000000, 24, 'bayar_sendiri', now());

        $this->assertSame('disetujui', $this->ajukan($anggota, 5000000, 12)->status);
    }

    public function test_cicilan_kredit_potong_gaji_mengurangi_batas_potong_gaji_kasir(): void
    {
        $anggota = $this->user(['gaji' => 1000000]); // batas 500 rb
        $this->ajukan($anggota, 3600000, 12, 'potong_gaji'); // 336 rb di 2026-02

        $this->assertFalse(Piutang::wouldExceedLimit($anggota, '2026-02', 100000));
        $this->assertTrue(Piutang::wouldExceedLimit($anggota, '2026-02', 200000));
    }

    // --- laporan -----------------------------------------------------------------

    public function test_laporan_bulanan_memuat_angsuran_lunas_dan_administrasi(): void
    {
        $a = $this->user(['name' => 'Ani']);
        $b = $this->user(['name' => 'Budi']);
        $this->ajukan($a, 3600000, 12);
        $kreditB = $this->ajukan($b, 1000000, 10);
        $this->service()->lunasiDipercepat($kreditB);

        $februari = $this->service()->laporanBulanan('2026-02');
        // Kredit Budi sudah dilunasi, jadi angsuran Feb-nya tidak lagi jatuh tempo.
        $this->assertCount(1, $februari['per_anggota']);
        $this->assertEquals(300000, $februari['total_pokok']);
        $this->assertEquals(36000, $februari['total_bunga']);
        $this->assertEquals(336000, $februari['per_anggota'][0]['total']);
        $this->assertEquals(3600000 - 300000, $februari['per_anggota'][0]['sisa_pokok']);

        $januari = $this->service()->laporanBulanan('2026-01');
        $this->assertEquals(72000 + 20000, $januari['pendapatan_administrasi']);
        $this->assertCount(1, $januari['lunas']);
        $this->assertEquals(1000000, $januari['lunas_pokok']);
        $this->assertEquals(100000, $januari['lunas_bunga'], 'dilunasi sebelum bulan ke-10: bunga ditagih sampai bulan ke-10');
    }

    public function test_unit_laporan_menghitung_nilai_kredit_dan_pinjaman_di_rentang_tanggal(): void
    {
        $anggota = $this->user();
        $this->ajukan($anggota, 3000000, 12);
        $this->ajukan($anggota, 4000000, 12); // melebihi sisa plafon -> ditolak, tidak dihitung
        app(PinjamanService::class)->ajukan($anggota, 10000000, 24, 'bayar_sendiri', now());

        $dari = Carbon::parse('2026-01-01');
        $sampai = Carbon::parse('2026-01-31');

        $this->assertEquals(3000000, (new KreditUnitLaporan)->omset($dari, $sampai));
        $this->assertEquals(10000000, (new SimpanPinjamUnitLaporan)->omset($dari, $sampai));
        $this->assertEquals(0, (new KreditUnitLaporan)->omset(Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28')));
    }

    // --- HTTP --------------------------------------------------------------------

    public function test_alur_http_admin_simulasi_ajukan_bayar_lunas_dan_invoice_pdf(): void
    {
        $admin = $this->admin();
        $anggota = $this->user(['name' => 'Citra Lestari']);
        $input = [
            'user_id' => $anggota->user_id, 'nama_barang' => 'Mesin Cuci', 'pokok' => 3600000,
            'tenor' => 12, 'metode_bayar' => 'potong_gaji',
        ];

        $this->actingAs($admin)->get(route('kredit.index', $input))
            ->assertOk()->assertSee('Hasil Simulasi')->assertSee('Ajukan Kredit');

        $this->actingAs($admin)->post(route('kredit.store'), $input)->assertRedirect();
        $kredit = KreditBarang::firstOrFail();
        $this->assertSame('disetujui', $kredit->status);
        $this->assertEquals($admin->user_id, $kredit->dibuat_oleh);

        $this->actingAs($admin)->get(route('kredit.show', $kredit))
            ->assertOk()->assertSee('Citra Lestari')->assertSee('Mesin Cuci');
        $this->actingAs($admin)->get(route('kredit.invoice', $kredit))->assertRedirect(route('kredit.show', $kredit));

        $this->actingAs($admin)->post(route('kredit.bayar-angsuran', $kredit->angsuran->first()))->assertRedirect();
        $this->actingAs($admin)->post(route('kredit.lunasi', $kredit))->assertRedirect();
        $this->assertSame('lunas', $kredit->fresh()->status);

        $pdf = $this->actingAs($admin)->get(route('kredit.invoice', $kredit));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $this->actingAs($admin)->get(route('kredit.laporan', ['bulan' => '2026-01']))
            ->assertOk()->assertSee('Citra Lestari')->assertSee('Mesin Cuci');
    }

    public function test_pengajuan_ditolak_lewat_http_menampilkan_alasan(): void
    {
        $admin = $this->admin();
        $anggota = $this->user();

        $this->actingAs($admin)->post(route('kredit.store'), [
            'user_id' => $anggota->user_id, 'nama_barang' => 'TV', 'pokok' => 9000000,
            'tenor' => 12, 'metode_bayar' => 'bayar_sendiri',
        ])->assertRedirect();

        $kredit = KreditBarang::firstOrFail();
        $this->assertSame('ditolak', $kredit->status);
        $this->actingAs($admin)->get(route('kredit.show', $kredit))->assertOk()->assertSee('Melebihi plafon tahunan');
    }

    public function test_input_tidak_valid_ditolak_validasi(): void
    {
        $admin = $this->admin();
        $anggota = $this->user();

        $this->actingAs($admin)->post(route('kredit.store'), [
            'user_id' => $anggota->user_id, 'nama_barang' => '', 'pokok' => 1000000,
            'tenor' => 24, 'metode_bayar' => 'tunai',
        ])->assertSessionHasErrors(['nama_barang', 'tenor', 'metode_bayar']);

        $this->assertSame(0, KreditBarang::count());
    }

    public function test_tandai_lunas_piutang_kredit_diteruskan_ke_angsuran_dan_menjaga_urutan(): void
    {
        $admin = $this->admin();
        $kredit = $this->ajukan($this->user(), 3600000, 12, 'potong_gaji');
        $piutang = Piutang::where('source_type', 'kredit')->orderBy('source_id')->get();

        $this->actingAs($admin)->put(route('piutang.tandai-lunas', $piutang[1]))->assertSessionHasErrors('angsuran');
        $this->assertSame('belum_lunas', $kredit->angsuran[1]->fresh()->status);

        $this->actingAs($admin)->put(route('piutang.tandai-lunas', $piutang[0]))->assertRedirect();
        $this->assertSame('lunas', $kredit->angsuran[0]->fresh()->status);
        $this->assertSame('lunas', $piutang[0]->fresh()->status);
    }

    public function test_karyawan_biasa_tidak_bisa_membuka_menu_kredit(): void
    {
        $karyawan = $this->user();

        $this->actingAs($karyawan)->get(route('kredit.index'))->assertRedirect();
        $this->actingAs($karyawan)->post(route('kredit.store'), [
            'user_id' => $karyawan->user_id, 'nama_barang' => 'TV', 'pokok' => 1000000,
            'tenor' => 10, 'metode_bayar' => 'bayar_sendiri',
        ])->assertRedirect();

        $this->assertSame(0, KreditBarang::count());
    }
}

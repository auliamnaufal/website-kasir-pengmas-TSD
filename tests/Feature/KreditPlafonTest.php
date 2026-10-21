<?php

namespace Tests\Feature;

use App\Models\KreditBarang;
use App\Models\User;
use App\Services\KreditPlafonService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

class KreditPlafonTest extends TestCase
{
    use RefreshDatabase;

    private function makeAnggota(): User
    {
        return User::create([
            'name' => 'Karyawan',
            'email' => 'karyawan+'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
        ]);
    }

    private function service(): KreditPlafonService
    {
        return app(KreditPlafonService::class);
    }

    public function test_pengajuan_di_bawah_plafon_disetujui(): void
    {
        $kredit = $this->service()->ajukan($this->makeAnggota(), 'Kulkas', 3000000, 12, Carbon::parse('2026-03-10'));

        $this->assertSame('disetujui', $kredit->status);
        $this->assertNull($kredit->alasan_penolakan);
        $this->assertDatabaseHas('kredit_barang', ['id' => $kredit->id, 'status' => 'disetujui']);
    }

    public function test_pengajuan_tepat_sebesar_plafon_disetujui(): void
    {
        $kredit = $this->service()->ajukan($this->makeAnggota(), 'Motor', 5000000, 10, Carbon::parse('2026-03-10'));

        $this->assertSame('disetujui', $kredit->status);
    }

    public function test_pengajuan_melebihi_plafon_otomatis_ditolak_dengan_alasan(): void
    {
        $kredit = $this->service()->ajukan($this->makeAnggota(), 'Motor', 5000001, 12, Carbon::parse('2026-03-10'));

        $this->assertSame('ditolak', $kredit->status);
        $this->assertStringContainsString('plafon', $kredit->alasan_penolakan);
    }

    public function test_plafon_diakumulasi_antar_pengajuan_dalam_tahun_yang_sama(): void
    {
        $anggota = $this->makeAnggota();
        $this->service()->ajukan($anggota, 'Kulkas', 3000000, 12, Carbon::parse('2026-02-01'));

        $kedua = $this->service()->ajukan($anggota, 'TV', 2500000, 12, Carbon::parse('2026-09-01'));

        $this->assertSame('ditolak', $kedua->status);
        $this->assertSame(2000000.0, $this->service()->sisa($anggota, Carbon::parse('2026-09-01')));
    }

    public function test_pengajuan_ditolak_tidak_memakai_plafon(): void
    {
        $anggota = $this->makeAnggota();
        $this->service()->ajukan($anggota, 'Motor', 9000000, 12, Carbon::parse('2026-02-01')); // ditolak

        $ok = $this->service()->ajukan($anggota, 'Kulkas', 5000000, 12, Carbon::parse('2026-02-02'));

        $this->assertSame('disetujui', $ok->status);
    }

    public function test_plafon_direset_di_tahun_kalender_berikutnya(): void
    {
        $anggota = $this->makeAnggota();
        $this->service()->ajukan($anggota, 'Kulkas', 5000000, 12, Carbon::parse('2026-12-31'));

        $tahunDepan = $this->service()->ajukan($anggota, 'TV', 5000000, 12, Carbon::parse('2027-01-01'));

        $this->assertSame('disetujui', $tahunDepan->status);
    }

    public function test_plafon_masing_masing_anggota_terpisah(): void
    {
        $a = $this->makeAnggota();
        $b = $this->makeAnggota();
        $this->service()->ajukan($a, 'Kulkas', 5000000, 12, Carbon::parse('2026-03-10'));

        $kreditB = $this->service()->ajukan($b, 'TV', 5000000, 12, Carbon::parse('2026-03-10'));

        $this->assertSame('disetujui', $kreditB->status);
    }

    public function test_kredit_lunas_tetap_memakai_plafon_tahun_itu(): void
    {
        $anggota = $this->makeAnggota();
        $kredit = $this->service()->ajukan($anggota, 'Kulkas', 4000000, 12, Carbon::parse('2026-01-05'));
        $kredit->update(['status' => 'lunas']);

        $this->assertSame(4000000.0, $this->service()->terpakai($anggota, Carbon::parse('2026-06-01')));
    }

    public function test_administrasi_dua_persen_dari_pokok_dan_disimpan(): void
    {
        $kredit = $this->service()->ajukan($this->makeAnggota(), 'Kulkas', 3000000, 12, Carbon::parse('2026-03-10'));

        $this->assertEquals(60000.00, $kredit->administrasi);
        $this->assertSame(60000.0, $this->service()->hitungAdministrasi(3000000));
        $this->assertSame(33.33, $this->service()->hitungAdministrasi(1666.5)); // dibulatkan 2 desimal
    }

    public function test_administrasi_mengikuti_konfigurasi(): void
    {
        config(['kredit.administrasi_persen' => 3]);

        $this->assertSame(90000.0, $this->service()->hitungAdministrasi(3000000));
    }

    public function test_plafon_mengikuti_konfigurasi(): void
    {
        config(['kredit.plafon_tahunan' => 1000000]);

        $kredit = $this->service()->ajukan($this->makeAnggota(), 'HP', 1500000, 10, Carbon::parse('2026-03-10'));

        $this->assertSame('ditolak', $kredit->status);
    }

    public function test_tenor_di_luar_rentang_ditolak_dan_tidak_menyimpan_apa_apa(): void
    {
        $anggota = $this->makeAnggota();

        foreach ([9, 13] as $tenor) {
            try {
                $this->service()->ajukan($anggota, 'Kulkas', 1000000, $tenor, Carbon::parse('2026-03-10'));
                $this->fail("Tenor {$tenor} seharusnya ditolak.");
            } catch (InvalidArgumentException) {
                // diharapkan
            }
        }

        $this->assertSame(0, KreditBarang::count());
    }

    public function test_tenor_batas_10_dan_12_diterima(): void
    {
        $anggota = $this->makeAnggota();

        foreach ([10, 12] as $tenor) {
            $kredit = $this->service()->ajukan($anggota, 'Barang', 1000000, $tenor, Carbon::parse('2026-03-10'));
            $this->assertSame($tenor, $kredit->tenor);
        }
    }

    public function test_pokok_nol_atau_negatif_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->service()->ajukan($this->makeAnggota(), 'Kulkas', 0, 12, Carbon::parse('2026-03-10'));
    }

    public function test_kolom_kode_perkiraan_nullable_di_tabel_kredit(): void
    {
        $kredit = $this->service()->ajukan($this->makeAnggota(), 'Kulkas', 1000000, 12, Carbon::parse('2026-03-10'));

        $this->assertNull($kredit->fresh()->kode_perkiraan);
    }
}

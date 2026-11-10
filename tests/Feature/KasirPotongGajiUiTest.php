<?php

namespace Tests\Feature;

use App\Models\JabatanConfig;
use App\Models\Piutang;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KasirPotongGajiUiTest extends TestCase
{
    use RefreshDatabase;

    private function makePegawai(string $email, ?int $gaji = 1000000): User
    {
        JabatanConfig::firstOrCreate(['jabatan' => 'Staff'], [
            'level' => 1,
            'simpanan_wajib' => 100000,
            'max_potongan_persen' => 50,
        ]);

        return User::create([
            'name' => 'Pegawai ' . $email,
            'email' => $email,
            'password' => 'secret',
            'role' => 'karyawan',
            'jabatan' => 'Staff',
            'gaji' => $gaji,
        ]);
    }

    public function test_sisa_batas_subtracts_belum_lunas_piutang_in_the_period(): void
    {
        $pegawai = $this->makePegawai('a@example.com');
        $period = Piutang::periodFor(now());
        Piutang::create([
            'user_id' => $pegawai->user_id,
            'source_type' => 'pembelian',
            'source_id' => 1,
            'amount' => 200000,
            'status' => 'belum_lunas',
            'period' => $period,
        ]);

        // batas 50% x 1.000.000 = 500.000; sudah terpakai 200.000
        $this->assertEquals(300000, Piutang::sisaBatas($pegawai, $period));
        $this->assertFalse(Piutang::wouldExceedLimit($pegawai, $period, 300000));
        $this->assertTrue(Piutang::wouldExceedLimit($pegawai, $period, 300001));
    }

    public function test_sisa_batas_is_null_without_gaji(): void
    {
        $pegawai = $this->makePegawai('b@example.com', null);

        $this->assertNull(Piutang::sisaBatas($pegawai, Piutang::periodFor(now())));
    }

    public function test_kasir_page_offers_potong_gaji_with_eligible_pegawai(): void
    {
        $admin = $this->makePegawai('admin-kasir@example.com');
        $this->makePegawai('tanpa-gaji@example.com', null);

        $response = $this->actingAs($admin)->get(route('kasir.index'));

        $response->assertOk();
        $response->assertSee('Pembayaran Potong Gaji');
        $response->assertSee('Pegawai admin-kasir@example.com');
        $response->assertSee('data-sisa="500000"', false);
        $response->assertDontSee('Pegawai tanpa-gaji@example.com');
    }
}

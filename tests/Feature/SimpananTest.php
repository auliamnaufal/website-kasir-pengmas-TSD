<?php

namespace Tests\Feature;

use App\Models\JabatanConfig;
use App\Models\Simpanan;
use App\Models\User;
use App\Services\SimpananService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SimpananTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-01-20 09:00:00');
        foreach ([['Staff', 1, 100000], ['Kasubag', 2, 150000], ['Kabag', 3, 200000], ['Direksi', 4, 250000]] as [$jabatan, $level, $wajib]) {
            JabatanConfig::create(['jabatan' => $jabatan, 'level' => $level, 'simpanan_wajib' => $wajib, 'max_potongan_persen' => 50]);
        }
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
        ], $overrides));
    }

    public function test_simpanan_wajib_mengikuti_nominal_jabatan_dan_tidak_dobel(): void
    {
        $staff = $this->user(['jabatan' => 'Staff']);
        $direksi = $this->user(['jabatan' => 'Direksi']);
        $tanpaJabatan = $this->user();
        $service = app(SimpananService::class);

        $this->assertSame(2, $service->generateWajib('2026-02'));
        $this->assertSame(0, $service->generateWajib('2026-02'));

        $this->assertDatabaseHas('simpanan', ['user_id' => $staff->user_id, 'jenis' => 'wajib', 'periode' => '2026-02', 'jumlah' => 100000]);
        $this->assertDatabaseHas('simpanan', ['user_id' => $direksi->user_id, 'jenis' => 'wajib', 'periode' => '2026-02', 'jumlah' => 250000]);
        $this->assertDatabaseMissing('simpanan', ['user_id' => $tanpaJabatan->user_id]);
    }

    public function test_command_generate_wajib_memakai_periode_cutoff_berjalan(): void
    {
        $staff = $this->user(['jabatan' => 'Staff']);

        // 20 Januari masuk window 16 Jan - 15 Feb, jadi periode 2026-02.
        $this->artisan('simpanan:generate-wajib')->assertSuccessful();

        $this->assertDatabaseHas('simpanan', ['user_id' => $staff->user_id, 'jenis' => 'wajib', 'periode' => '2026-02']);
    }

    public function test_saldo_dipisah_wajib_dan_sukarela(): void
    {
        $staff = $this->user(['jabatan' => 'Staff']);
        $service = app(SimpananService::class);
        $service->generateWajib('2026-01');
        $service->generateWajib('2026-02');
        $service->setorSukarela($staff, 50000, now());

        $this->assertSame(['wajib' => 200000.0, 'sukarela' => 50000.0, 'total' => 250000.0], $service->saldo($staff));
    }

    public function test_admin_mencatat_simpanan_lewat_halaman(): void
    {
        $admin = $this->user(['role' => 'admin']);
        $staff = $this->user(['jabatan' => 'Staff', 'name' => 'Siti Aminah']);

        $this->actingAs($admin)->post(route('simpanan.generate-wajib'), ['periode' => '2026-02'])->assertRedirect();
        $this->actingAs($admin)->post(route('simpanan.sukarela'), ['user_id' => $staff->user_id, 'jumlah' => 75000])->assertRedirect();

        $this->assertSame(2, Simpanan::where('user_id', $staff->user_id)->count());
        $this->actingAs($admin)->get(route('simpanan.index'))->assertOk()->assertSee('Siti Aminah')->assertSee('Rp175.000');
    }

    public function test_non_admin_tidak_bisa_mencatat_simpanan(): void
    {
        $karyawan = $this->user(['jabatan' => 'Staff']);

        $this->actingAs($karyawan)->post(route('simpanan.sukarela'), ['user_id' => $karyawan->user_id, 'jumlah' => 75000])->assertRedirect();

        $this->assertDatabaseCount('simpanan', 0);
    }
}

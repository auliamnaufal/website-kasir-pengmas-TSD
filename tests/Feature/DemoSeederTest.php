<?php

namespace Tests\Feature;

use App\Models\KreditBarang;
use App\Models\Pinjaman;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seeder demo dipakai untuk QA end-to-end. Test ini menjaganya tetap jalan
 * saat aturan service berubah, dan menjamin seeder aman dijalankan ulang.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_lengkap_menghasilkan_semua_status_di_kedua_unit(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['disetujui', 'lunas', 'ditolak'] as $status) {
            $this->assertTrue(KreditBarang::where('status', $status)->exists(), "kredit {$status}");
        }
        foreach (['aktif', 'lunas', 'ditolak'] as $status) {
            $this->assertTrue(Pinjaman::where('status', $status)->exists(), "pinjaman {$status}");
        }
    }

    public function test_seed_ulang_tidak_menggandakan_data(): void
    {
        $this->seed(DatabaseSeeder::class);
        $kredit = KreditBarang::count();
        $pinjaman = Pinjaman::count();

        $this->seed(\Database\Seeders\DemoSeeder::class);

        $this->assertSame($kredit, KreditBarang::count());
        $this->assertSame($pinjaman, Pinjaman::count());
    }
}

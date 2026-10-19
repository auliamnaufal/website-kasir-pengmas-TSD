<?php

namespace Tests\Feature;

use App\Models\JabatanConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JabatanConfigTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);
    }

    public function test_admin_can_view_jabatan_configs(): void
    {
        JabatanConfig::create([
            'jabatan' => 'Staff',
            'level' => 1,
            'simpanan_wajib' => 100000,
            'max_potongan_persen' => 50,
        ]);

        $response = $this->actingAs($this->admin())->get(route('jabatan-config.index'));

        $response->assertStatus(200);
        $response->assertSee('Staff');
        $response->assertSee('100000');
    }

    public function test_admin_can_update_a_jabatan_config_and_it_is_read_back_on_next_request(): void
    {
        $config = JabatanConfig::create([
            'jabatan' => 'Staff',
            'level' => 1,
            'simpanan_wajib' => 100000,
            'max_potongan_persen' => 50,
        ]);

        $admin = $this->admin();

        $updateResponse = $this->actingAs($admin)->put(route('jabatan-config.update', $config), [
            'level' => 1,
            'simpanan_wajib' => 120000,
            'max_potongan_persen' => 60,
        ]);

        $updateResponse->assertRedirect(route('jabatan-config.index'));
        $this->assertDatabaseHas('jabatan_configs', [
            'jabatan' => 'Staff',
            'simpanan_wajib' => 120000,
            'max_potongan_persen' => 60,
        ]);

        $nextResponse = $this->actingAs($admin)->get(route('jabatan-config.index'));
        $nextResponse->assertStatus(200);
        $nextResponse->assertSee('120000');
        $nextResponse->assertSee('60');
    }

    public function test_seeder_fills_four_default_jabatan_configs(): void
    {
        $this->seed(\Database\Seeders\JabatanConfigSeeder::class);

        $this->assertDatabaseCount('jabatan_configs', 4);
        $this->assertDatabaseHas('jabatan_configs', ['jabatan' => 'Staff', 'level' => 1, 'simpanan_wajib' => 100000]);
        $this->assertDatabaseHas('jabatan_configs', ['jabatan' => 'Kasubag', 'level' => 2, 'simpanan_wajib' => 150000]);
        $this->assertDatabaseHas('jabatan_configs', ['jabatan' => 'Kabag', 'level' => 3, 'simpanan_wajib' => 200000]);
        $this->assertDatabaseHas('jabatan_configs', ['jabatan' => 'Direksi', 'level' => 4, 'simpanan_wajib' => 250000]);
    }

    public function test_non_admin_cannot_view_or_update_jabatan_configs(): void
    {
        $config = JabatanConfig::create([
            'jabatan' => 'Staff',
            'level' => 1,
            'simpanan_wajib' => 100000,
            'max_potongan_persen' => 50,
        ]);

        $karyawan = User::create([
            'name' => 'Karyawan',
            'email' => 'karyawan@example.com',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
        ]);

        $this->actingAs($karyawan)->get(route('jabatan-config.index'))->assertRedirect();
        $this->actingAs($karyawan)->put(route('jabatan-config.update', $config), [
            'level' => 1,
            'simpanan_wajib' => 999999,
            'max_potongan_persen' => 99,
        ])->assertRedirect();

        $this->assertDatabaseHas('jabatan_configs', ['jabatan' => 'Staff', 'simpanan_wajib' => 100000]);
    }
}

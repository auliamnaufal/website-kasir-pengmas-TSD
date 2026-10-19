<?php

namespace Tests\Feature;

use App\Models\JabatanConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateGajiJabatanAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function seedJabatanConfigs(): void
    {
        JabatanConfig::create(['jabatan' => 'Staff', 'level' => 1, 'simpanan_wajib' => 100000, 'max_potongan_persen' => 50]);
        JabatanConfig::create(['jabatan' => 'Kabag', 'level' => 3, 'simpanan_wajib' => 200000, 'max_potongan_persen' => 50]);
    }

    private function user(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'User',
            'email' => 'user'.uniqid().'@example.com',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
        ], $overrides));
    }

    public function test_admin_can_update_any_karyawan_gaji_and_jabatan(): void
    {
        $this->seedJabatanConfigs();
        $admin = $this->user(['role' => 'admin', 'jabatan' => 'Staff', 'gaji' => 1000000]);
        $staff = $this->user(['jabatan' => 'Staff', 'gaji' => 3000000]);

        $response = $this->actingAs($admin)->put(route('karyawan.update-gaji-jabatan', $staff), [
            'gaji' => 3500000,
            'jabatan' => 'Kabag',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'user_id' => $staff->user_id,
            'gaji' => 3500000,
            'jabatan' => 'Kabag',
        ]);
    }

    public function test_non_admin_with_higher_jabatan_level_can_update_lower_level_target(): void
    {
        $this->seedJabatanConfigs();
        $kabag = $this->user(['jabatan' => 'Kabag', 'gaji' => 5000000]);
        $staff = $this->user(['jabatan' => 'Staff', 'gaji' => 3000000]);

        $response = $this->actingAs($kabag)->put(route('karyawan.update-gaji-jabatan', $staff), [
            'gaji' => 3200000,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('users', [
            'user_id' => $staff->user_id,
            'gaji' => 3200000,
        ]);
    }

    public function test_non_admin_with_equal_or_lower_jabatan_level_is_denied(): void
    {
        $this->seedJabatanConfigs();
        $staffActor = $this->user(['jabatan' => 'Staff', 'gaji' => 3000000]);
        $staffTarget = $this->user(['jabatan' => 'Staff', 'gaji' => 3000000]);

        $response = $this->actingAs($staffActor)->put(route('karyawan.update-gaji-jabatan', $staffTarget), [
            'gaji' => 3200000,
        ]);

        $response->assertStatus(403);
        $this->assertDatabaseHas('users', [
            'user_id' => $staffTarget->user_id,
            'gaji' => 3000000,
        ]);
    }

    public function test_jabatan_must_be_a_configured_jabatan(): void
    {
        $this->seedJabatanConfigs();
        $admin = $this->user(['role' => 'admin', 'jabatan' => 'Staff', 'gaji' => 1000000]);
        $staff = $this->user(['jabatan' => 'Staff', 'gaji' => 3000000]);

        $response = $this->actingAs($admin)->put(route('karyawan.update-gaji-jabatan', $staff), [
            'jabatan' => 'NotConfigured',
        ]);

        $response->assertSessionHasErrors('jabatan');
        $this->assertDatabaseHas('users', ['user_id' => $staff->user_id, 'jabatan' => 'Staff']);
    }
}

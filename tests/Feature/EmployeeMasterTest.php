<?php

namespace Tests\Feature;

use App\Models\JabatanConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeMasterTest extends TestCase
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

    private function staffJabatan(): JabatanConfig
    {
        return JabatanConfig::create([
            'jabatan' => 'Staff',
            'level' => 1,
            'simpanan_wajib' => 100000,
            'max_potongan_persen' => 50,
        ]);
    }

    public function test_admin_can_create_karyawan_with_employee_id_auto_generated(): void
    {
        $this->staffJabatan();

        $response = $this->actingAs($this->admin())->post(route('karyawan.store'), [
            'name' => 'Budi',
            'email' => 'budi@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nik' => '1234567890123456',
            'jabatan' => 'Staff',
            'gaji' => 3000000,
        ]);

        $response->assertRedirect(route('karyawan.index'));

        $this->assertDatabaseHas('users', [
            'email' => 'budi@gmail.com',
            'nik' => '1234567890123456',
            'jabatan' => 'Staff',
            'gaji' => 3000000,
        ]);

        $this->assertMatchesRegularExpression('/^EMP\d{5}$/', User::where('email', 'budi@gmail.com')->value('employee_id'));
    }

    public function test_employee_id_sequential_across_karyawan_created(): void
    {
        $this->staffJabatan();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('karyawan.store'), [
            'name' => 'Budi',
            'email' => 'budi@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nik' => '1111111111111111',
            'jabatan' => 'Staff',
            'gaji' => 3000000,
        ]);

        $this->actingAs($admin)->post(route('karyawan.store'), [
            'name' => 'Ani',
            'email' => 'ani@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nik' => '2222222222222222',
            'jabatan' => 'Staff',
            'gaji' => 3000000,
        ]);

        $budiNumber = (int) substr(User::where('email', 'budi@gmail.com')->value('employee_id'), 3);
        $aniNumber = (int) substr(User::where('email', 'ani@gmail.com')->value('employee_id'), 3);

        $this->assertSame($budiNumber + 1, $aniNumber);
    }

    public function test_duplicate_nik_is_rejected(): void
    {
        $this->staffJabatan();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('karyawan.store'), [
            'name' => 'Budi',
            'email' => 'budi@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nik' => '1234567890123456',
            'jabatan' => 'Staff',
            'gaji' => 3000000,
        ]);

        $response = $this->actingAs($admin)->post(route('karyawan.store'), [
            'name' => 'Ani',
            'email' => 'ani@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nik' => '1234567890123456',
            'jabatan' => 'Staff',
            'gaji' => 3000000,
        ]);

        $response->assertSessionHasErrors('nik');
        $this->assertDatabaseMissing('users', ['email' => 'ani@gmail.com']);
    }

    public function test_jabatan_must_be_one_of_configured_jabatan_configs(): void
    {
        $this->staffJabatan();

        $response = $this->actingAs($this->admin())->post(route('karyawan.store'), [
            'name' => 'Budi',
            'email' => 'budi@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nik' => '1234567890123456',
            'jabatan' => 'NotConfigured',
            'gaji' => 3000000,
        ]);

        $response->assertSessionHasErrors('jabatan');
        $this->assertDatabaseMissing('users', ['email' => 'budi@gmail.com']);
    }

    public function test_employee_id_cannot_be_set_manually_through_the_form(): void
    {
        $this->staffJabatan();

        $this->actingAs($this->admin())->post(route('karyawan.store'), [
            'name' => 'Budi',
            'email' => 'budi@gmail.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'nik' => '1234567890123456',
            'jabatan' => 'Staff',
            'gaji' => 3000000,
            'employee_id' => 'EMP99999',
        ]);

        $this->assertDatabaseMissing('users', ['employee_id' => 'EMP99999']);
        $this->assertMatchesRegularExpression('/^EMP\d{5}$/', User::where('email', 'budi@gmail.com')->value('employee_id'));
    }

    public function test_karyawan_create_form_is_reachable(): void
    {
        $this->staffJabatan();

        $response = $this->actingAs($this->admin())->get(route('karyawan.create'));

        $response->assertStatus(200);
        $response->assertSee('name="nik"', false);
        $response->assertSee('name="jabatan"', false);
        $response->assertSee('name="gaji"', false);
        $response->assertSee('Staff');
    }

    public function test_admin_can_edit_karyawan_data(): void
    {
        $this->staffJabatan();
        JabatanConfig::create([
            'jabatan' => 'Kasubag',
            'level' => 2,
            'simpanan_wajib' => 150000,
            'max_potongan_persen' => 50,
        ]);

        $karyawan = User::create([
            'name' => 'Budi',
            'email' => 'budi@gmail.com',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
            'nik' => '1111111111111111',
            'jabatan' => 'Staff',
            'gaji' => 3000000,
        ]);

        $response = $this->actingAs($this->admin())->put(route('karyawan.update', $karyawan->user_id), [
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@gmail.com',
            'nik' => '2222222222222222',
            'jabatan' => 'Kasubag',
            'gaji' => 4000000,
        ]);

        $response->assertRedirect(route('karyawan.index'));
        $this->assertDatabaseHas('users', [
            'user_id' => $karyawan->user_id,
            'name' => 'Budi Santoso',
            'email' => 'budi.santoso@gmail.com',
            'nik' => '2222222222222222',
            'jabatan' => 'Kasubag',
            'gaji' => 4000000,
        ]);
    }

    public function test_non_admin_cannot_edit_karyawan_data(): void
    {
        $this->staffJabatan();

        $karyawan = User::create([
            'name' => 'Budi',
            'email' => 'budi@gmail.com',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
            'nik' => '1111111111111111',
            'jabatan' => 'Staff',
            'gaji' => 3000000,
        ]);

        $other = User::create([
            'name' => 'Siti',
            'email' => 'siti@example.com',
            'password' => bcrypt('password'),
            'role' => 'karyawan',
            'jabatan' => 'Staff',
        ]);

        $response = $this->actingAs($other)->put(route('karyawan.update', $karyawan->user_id), [
            'name' => 'Hacked',
            'email' => 'budi@gmail.com',
            'nik' => '1111111111111111',
            'jabatan' => 'Staff',
            'gaji' => 999999999,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('users', [
            'user_id' => $karyawan->user_id,
            'name' => 'Budi',
            'gaji' => 3000000,
        ]);
    }
}

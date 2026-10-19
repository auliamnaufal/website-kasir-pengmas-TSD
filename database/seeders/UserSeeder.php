<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Uses User::create() (not DB::table()->insert()) so the employee_id auto-generation
     * hook on the model fires, same as it would for a real admin-created account.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'nik' => '3200000000000001',
            'jabatan' => 'Direksi',
            'gaji' => 10000000,
        ]);

        User::create([
            'name' => 'Karyawan User',
            'email' => 'karyawan@example.com',
            'password' => Hash::make('password'),
            'role' => 'karyawan',
            'nik' => '3200000000000002',
            'jabatan' => 'Staff',
            'gaji' => 3500000,
        ]);
    }
}

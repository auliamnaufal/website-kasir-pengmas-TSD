<?php

namespace Database\Seeders;

use App\Models\JabatanConfig;
use Illuminate\Database\Seeder;

class JabatanConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * max_potongan_persen is a placeholder (50%) until the client confirms the real figure.
     */
    public function run(): void
    {
        $defaults = [
            ['jabatan' => 'Staff', 'level' => 1, 'simpanan_wajib' => 100000, 'max_potongan_persen' => 50],
            ['jabatan' => 'Kasubag', 'level' => 2, 'simpanan_wajib' => 150000, 'max_potongan_persen' => 50],
            ['jabatan' => 'Kabag', 'level' => 3, 'simpanan_wajib' => 200000, 'max_potongan_persen' => 50],
            ['jabatan' => 'Direksi', 'level' => 4, 'simpanan_wajib' => 250000, 'max_potongan_persen' => 50],
        ];

        foreach ($defaults as $config) {
            JabatanConfig::updateOrCreate(['jabatan' => $config['jabatan']], $config);
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run()
    {
        $this->call([
            JabatanConfigSeeder::class,
            UserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            OrderSeeder::class,
        ]);

        // Data demo QA (simpanan, simpan pinjam, kredit). Tidak untuk produksi.
        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}

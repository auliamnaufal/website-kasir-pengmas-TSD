<?php

namespace Database\Seeders;

use App\Models\KreditBarang;
use App\Models\Pinjaman;
use App\Models\Piutang;
use App\Models\User;
use App\Services\KreditService;
use App\Services\PinjamanService;
use App\Services\SimpananService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Data demo untuk QA end-to-end semua unit: anggota dengan berbagai jabatan dan
 * gaji, simpanan wajib/sukarela, pinjaman (berjalan, lunas, kompensasi, ditolak),
 * dan kredit barang (berjalan, lunas, ditolak). Seluruhnya dibuat lewat service
 * yang sama dengan aplikasi, jadi aturan plafon, potong gaji, dan piutang ikut
 * berlaku. Transaksi toko dibuat OrderSeeder. Aman dijalankan ulang: dilewati
 * bila pinjaman atau kredit sudah ada.
 *
 * Jalankan sendiri: php artisan db:seed --class=DemoSeeder
 * Syarat: JabatanConfigSeeder sudah dijalankan.
 */
class DemoSeeder extends Seeder
{
    public function run(PinjamanService $pinjaman, KreditService $kredit, SimpananService $simpanan): void
    {
        if (Pinjaman::exists() || KreditBarang::exists()) {
            $this->command?->warn('DemoSeeder dilewati: data pinjaman/kredit sudah ada.');

            return;
        }

        $admin = User::where('role', 'admin')->first();
        $a = $this->anggota('Andi Pratama', 'Staff', 4000000, '3200000000000011');
        $b = $this->anggota('Bunga Lestari', 'Staff', 3000000, '3200000000000012');
        $c = $this->anggota('Candra Wijaya', 'Kasubag', 6000000, '3200000000000013');
        $d = $this->anggota('Dewi Anggraini', 'Kabag', 9000000, '3200000000000014');
        $e = $this->anggota('Eko Saputra', 'Staff', 500000, '3200000000000015'); // gaji kecil: potong gaji ditolak
        $f = $this->anggota('Fitri Handayani', 'Direksi', 15000000, '3200000000000016');

        // Simpanan: wajib 3 periode terakhir + sukarela.
        foreach ([2, 1, 0] as $mundur) {
            $simpanan->generateWajib(now()->subMonths($mundur)->format('Y-m'), $admin);
        }
        $simpanan->setorSukarela($d, 1500000, now()->subMonths(2), 'Setoran sukarela awal', $admin);
        $simpanan->setorSukarela($f, 3000000, now()->subMonth(), 'Setoran sukarela', $admin);

        // --- Simpan Pinjam ---
        // Andi: berjalan, bayar sendiri, 2 dari 10 angsuran terbayar.
        $p1 = $pinjaman->ajukan($a, 10000000, 10, 'bayar_sendiri', now()->subMonths(2), false, $admin);
        $this->bayar($p1->angsuran->take(2), fn ($x) => $pinjaman->bayarAngsuran($x, $admin));

        // Candra: berjalan, potong gaji (menulis piutang), lalu dikompensasi pinjaman baru.
        $p2 = $pinjaman->ajukan($c, 12000000, 12, 'potong_gaji', now()->subMonths(3), false, $admin);
        $this->bayar($p2->angsuran->take(3), fn ($x) => $pinjaman->bayarAngsuran($x, $admin));
        $pinjaman->ajukan($c, 20000000, 24, 'potong_gaji', now()->subMonth(), true, $admin);

        // Dewi: lunas dipercepat.
        $p3 = $pinjaman->ajukan($d, 8000000, 12, 'bayar_sendiri', now()->subMonths(4), false, $admin);
        $this->bayar($p3->angsuran->take(4), fn ($x) => $pinjaman->bayarAngsuran($x, $admin));
        $pinjaman->lunasiDipercepat($p3->fresh(), $admin);

        // Fitri: lunas penuh lewat angsuran terakhir.
        $p4 = $pinjaman->ajukan($f, 5000000, 10, 'bayar_sendiri', now()->subMonths(11), false, $admin);
        $this->bayar($p4->angsuran, fn ($x) => $pinjaman->bayarAngsuran($x, $admin));

        // Ditolak otomatis: melebihi plafon Rp50 jt, dan gaji kecil untuk potong gaji.
        $pinjaman->ajukan($f, 60000000, 36, 'bayar_sendiri', now()->subDays(5), false, $admin);
        $pinjaman->ajukan($e, 5000000, 10, 'potong_gaji', now()->subDays(3), false, $admin);

        // --- Kredit Pertokoan ---
        // Andi: berjalan, potong gaji, 3 dari 12 angsuran terbayar.
        $k1 = $kredit->ajukan($a, 'Kulkas 2 Pintu', 3600000, 12, 'potong_gaji', now()->subMonths(3), $admin);
        $this->bayar($k1->angsuran->take(3), fn ($x) => $kredit->bayarAngsuran($x, $admin));

        // Bunga: berjalan, bayar sendiri.
        $kredit->ajukan($b, 'Mesin Cuci', 2500000, 10, 'bayar_sendiri', now()->subMonths(1), $admin);

        // Dewi: lunas dipercepat.
        $k3 = $kredit->ajukan($d, 'Televisi 43"', 4000000, 12, 'bayar_sendiri', now()->subMonths(6), $admin);
        $this->bayar($k3->angsuran->take(5), fn ($x) => $kredit->bayarAngsuran($x, $admin));
        $kredit->lunasiDipercepat($k3->fresh(), $admin);

        // Candra: lunas penuh (tenor 12, bunga hanya sampai bulan ke-11).
        $k4 = $kredit->ajukan($c, 'Kipas Angin', 1200000, 12, 'bayar_sendiri', now()->subMonths(13), $admin);
        $this->bayar($k4->angsuran, fn ($x) => $kredit->bayarAngsuran($x, $admin));

        // Ditolak otomatis: melebihi plafon tahunan, dan potong gaji melebihi batas.
        $kredit->ajukan($f, 'Sofa', 6500000, 12, 'bayar_sendiri', now()->subDays(2), $admin);
        $kredit->ajukan($e, 'Kompor Gas', 3000000, 10, 'potong_gaji', now()->subDays(1), $admin);

        // Piutang pembelian toko (potong gaji di kasir) supaya batas potongan terlihat terpakai.
        Piutang::create([
            'user_id' => $b->user_id, 'source_type' => 'pembelian', 'source_id' => 1,
            'amount' => 150000, 'status' => 'belum_lunas', 'period' => Piutang::periodFor(now()),
        ]);
    }

    private function anggota(string $nama, string $jabatan, int $gaji, string $nik): User
    {
        $email = strtolower(explode(' ', $nama)[0]).'@example.com';

        return User::firstOrCreate(['email' => $email], [
            'name' => $nama,
            'password' => Hash::make('password'),
            'role' => 'karyawan',
            'nik' => $nik,
            'jabatan' => $jabatan,
            'gaji' => $gaji,
        ]);
    }

    /** Jalankan $aksi untuk tiap angsuran, berurutan (angsuran harus dibayar urut). */
    private function bayar($angsuran, callable $aksi): void
    {
        foreach ($angsuran as $item) {
            $aksi($item);
        }
    }
}

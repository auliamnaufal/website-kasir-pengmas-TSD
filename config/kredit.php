<?php

/*
 * Aturan Kredit Pertokoan (issue #2). Semua angka di sini sengaja bisa diubah
 * lewat .env karena beberapa aturan belum dijawab klien — jangan di-hardcode
 * di kode layanan.
 */
return [
    // Plafon pokok kredit barang per anggota dalam satu tahun kalender.
    // Open question klien: apakah digabung dengan plafon simpan pinjam (Rp50 jt)?
    'plafon_tahunan' => env('KREDIT_PLAFON_TAHUNAN', 5000000),

    'tenor_min' => env('KREDIT_TENOR_MIN', 10),
    'tenor_max' => env('KREDIT_TENOR_MAX', 12),

    // Administrasi dipotong sekali per pinjaman, persen dari pokok.
    'administrasi_persen' => env('KREDIT_ADMINISTRASI_PERSEN', 2),

    // Bunga persen dari pokok. Flat vs efektif dan bunga sampai bulan ke-11
    // masih open question; rumusnya dikerjakan mesin angsuran P2.
    'bunga_persen' => env('KREDIT_BUNGA_PERSEN', 1),
];

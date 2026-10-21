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

    // Bunga persen per bulan.
    'bunga_persen' => env('KREDIT_BUNGA_PERSEN', 1),

    // Open question #1: 'flat' (dari pokok awal) atau 'efektif' (dari sisa pokok).
    'metode_bunga' => env('KREDIT_METODE_BUNGA', 'flat'),

    // Bunga hanya dihitung sampai bulan ke-n; bulan sesudahnya bunganya 0.
    // Aturan klien: tenor 12 bulan dihitung bunga sampai bulan ke-11. Tenor
    // 10–11 tidak terpengaruh. Kosongkan (null) untuk bunga sepanjang tenor.
    'bunga_sampai_bulan' => env('KREDIT_BUNGA_SAMPAI_BULAN', 11),

    // Open question #2: pelunasan sebelum bulan ini tetap ditagih bunga sampai
    // bulan ini (bunga bulan berikutnya dibebaskan). Sama dengan default P2.
    'bulan_bunga_minimum' => env('KREDIT_BULAN_BUNGA_MINIMUM', 10),
];

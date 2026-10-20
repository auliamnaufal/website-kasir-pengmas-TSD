<?php

/*
 * Aturan Unit Simpan Pinjam (issue #1). Beberapa aturan masih open question
 * klien, jadi semua angka dan pilihan rumus bisa diubah lewat .env — jangan
 * di-hardcode di kode layanan.
 */
return [
    'tenor_min' => env('SP_TENOR_MIN', 10),
    'tenor_max' => env('SP_TENOR_MAX', 60),

    // Bunga persen per bulan.
    'bunga_persen' => env('SP_BUNGA_PERSEN', 1),

    // Open question #1: 'flat' (dari pokok awal) atau 'efektif' (dari sisa pokok).
    'metode_bunga' => env('SP_METODE_BUNGA', 'flat'),

    // Administrasi dipotong sekali per pinjaman, persen dari pokok. Dipecah ke
    // pendapatan administrasi koperasi dan simpanan sukarela anggota.
    'administrasi_pendapatan_persen' => env('SP_ADMINISTRASI_PENDAPATAN_PERSEN', 1),
    'administrasi_sukarela_persen' => env('SP_ADMINISTRASI_SUKARELA_PERSEN', 2),

    // Open question #2: pelunasan sebelum bulan ini tetap ditagih bunga sampai
    // bulan ini (bunga bulan berikutnya dibebaskan).
    'bulan_bunga_minimum' => env('SP_BULAN_BUNGA_MINIMUM', 10),

    // Open question #3: batas plafon per anggota. Pengajuan yang membuat angka
    // terpakai melewati batas ini otomatis ditolak.
    'plafon' => env('SP_PLAFON', 50000000),

    // 'outstanding' = sisa pokok semua pinjaman aktif (bunyi issue #1).
    // 'tahunan'     = total pokok pinjaman disetujui/lunas di tahun kalender
    //                 pengajuan (sama dengan konvensi kredit barang P3).
    'plafon_basis' => env('SP_PLAFON_BASIS', 'outstanding'),
];

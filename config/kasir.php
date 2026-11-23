<?php

return [
    /*
     * Identitas yang dicetak di kepala struk kasir. Semua bisa ditimpa lewat
     * .env tanpa mengubah view.
     */
    'nama_toko' => env('KASIR_NAMA_TOKO', 'Kasir UMKM TSD'),

    'situs' => env('KASIR_SITUS', 'kasir.kopkartirtasuci.com'),

    'alamat' => env('KASIR_ALAMAT', ''),

    'catatan' => env('KASIR_CATATAN_STRUK', 'Struk ini bukti pembayaran yang sah.'),

    'label_metode' => [
        'cash' => 'Tunai',
        'qris' => 'QRIS',
        'potong_gaji' => 'Potong Gaji',
    ],
];

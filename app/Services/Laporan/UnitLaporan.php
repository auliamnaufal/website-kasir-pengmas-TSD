<?php

namespace App\Services\Laporan;

use Carbon\CarbonInterface;

/**
 * Satu unit usaha yang ikut dalam laporan lintas unit (toko, simpan pinjam,
 * kredit). Unit baru cukup mengimplementasikan interface ini dan didaftarkan
 * dengan tag `laporan.unit` di AppServiceProvider; LaporanService tidak perlu
 * diubah.
 */
interface UnitLaporan
{
    public function nama(): string;

    /** Omset (rupiah) unit ini untuk rentang tanggal [$dari, $sampai], inklusif. */
    public function omset(CarbonInterface $dari, CarbonInterface $sampai): float;
}

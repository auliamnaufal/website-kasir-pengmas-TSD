<?php

namespace App\Services\Laporan;

use App\Models\KreditBarang;
use Carbon\CarbonInterface;

/**
 * Omset Kredit Pertokoan = nilai barang yang dikreditkan (pokok kredit
 * disetujui atau lunas) pada tanggal pengajuan, setara penjualan toko.
 * Kredit ditolak tidak dihitung.
 */
class KreditUnitLaporan implements UnitLaporan
{
    public function nama(): string
    {
        return 'Kredit Pertokoan';
    }

    public function omset(CarbonInterface $dari, CarbonInterface $sampai): float
    {
        return (float) KreditBarang::whereIn('status', ['disetujui', 'lunas'])
            ->whereBetween('tanggal_pengajuan', [$dari->toDateString(), $sampai->toDateString()])
            ->sum('pokok');
    }
}

<?php

namespace App\Services\Laporan;

use App\Models\Pinjaman;
use Carbon\CarbonInterface;

/**
 * Omset Simpan Pinjam = pokok pinjaman yang dicairkan (aktif atau lunas)
 * pada tanggal pengajuan. Pinjaman ditolak tidak dihitung. Pendapatan
 * administrasi dan bunga ada di Laporan Pelunasan Bulanan unit ini.
 */
class SimpanPinjamUnitLaporan implements UnitLaporan
{
    public function nama(): string
    {
        return 'Simpan Pinjam';
    }

    public function omset(CarbonInterface $dari, CarbonInterface $sampai): float
    {
        return (float) Pinjaman::whereIn('status', ['aktif', 'lunas'])
            ->whereBetween('tanggal_pengajuan', [$dari->toDateString(), $sampai->toDateString()])
            ->sum('pokok');
    }
}

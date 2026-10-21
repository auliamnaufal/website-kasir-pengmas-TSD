<?php

namespace App\Services;

use App\Models\KreditBarang;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Aturan plafon dan biaya kredit barang. Tidak menghitung jadwal angsuran:
 * itu tugas mesin angsuran P2.
 *
 * Plafon dihitung per tahun kalender dari pokok kredit yang tidak ditolak
 * (disetujui atau lunas). Kredit yang sudah lunas tetap memakai plafon tahun
 * itu. Kedua aturan ini open question klien, jadi ubah di sini saja.
 */
class KreditPlafonService
{
    public function plafonTahunan(): float
    {
        return (float) config('kredit.plafon_tahunan');
    }

    /** Pokok kredit yang sudah memakai plafon anggota pada tahun $tanggal. */
    public function terpakai(User $anggota, CarbonInterface $tanggal): float
    {
        return (float) KreditBarang::where('user_id', $anggota->user_id)
            ->whereIn('status', ['disetujui', 'lunas'])
            ->whereBetween('tanggal_pengajuan', [
                $tanggal->copy()->startOfYear()->toDateString(),
                $tanggal->copy()->endOfYear()->toDateString(),
            ])
            ->sum('pokok');
    }

    public function sisa(User $anggota, CarbonInterface $tanggal): float
    {
        return max(0.0, $this->plafonTahunan() - $this->terpakai($anggota, $tanggal));
    }

    /** Administrasi sekali per pinjaman, persen dari pokok. */
    public function hitungAdministrasi(float $pokok): float
    {
        return round($pokok * (float) config('kredit.administrasi_persen') / 100, 2);
    }

    /**
     * Catat pengajuan kredit. Otomatis ditolak (status `ditolak`) bila pokok
     * melewati sisa plafon tahunan; kredit ditolak tidak memakai plafon.
     *
     * @param  array<string, mixed>  $tambahan  atribut kredit lain yang ikut disimpan
     *                                          (metode bayar, parameter jadwal, dst.)
     *
     * @throws InvalidArgumentException bila pokok <= 0 atau tenor di luar rentang
     */
    public function ajukan(
        User $anggota,
        string $namaBarang,
        float $pokok,
        int $tenor,
        CarbonInterface $tanggal,
        array $tambahan = [],
    ): KreditBarang {
        if ($pokok <= 0) {
            throw new InvalidArgumentException('Pokok kredit harus lebih dari 0.');
        }

        $tenorMin = (int) config('kredit.tenor_min');
        $tenorMax = (int) config('kredit.tenor_max');
        if ($tenor < $tenorMin || $tenor > $tenorMax) {
            throw new InvalidArgumentException("Tenor harus antara {$tenorMin} dan {$tenorMax} bulan.");
        }

        return DB::transaction(function () use ($anggota, $namaBarang, $pokok, $tenor, $tanggal, $tambahan) {
            // Kunci baris anggota supaya dua pengajuan bersamaan tidak sama-sama
            // lolos membaca sisa plafon yang sama.
            User::where('user_id', $anggota->user_id)->lockForUpdate()->first();

            $sisa = $this->sisa($anggota, $tanggal);
            $ditolak = $pokok > $sisa;

            return KreditBarang::create([
                'user_id' => $anggota->user_id,
                'nama_barang' => $namaBarang,
                'pokok' => $pokok,
                'tenor' => $tenor,
                'administrasi' => $this->hitungAdministrasi($pokok),
                'bunga_persen' => config('kredit.bunga_persen'),
                'tanggal_pengajuan' => $tanggal->toDateString(),
                'status' => $ditolak ? 'ditolak' : 'disetujui',
                'alasan_penolakan' => $ditolak
                    ? $this->alasanMelebihiPlafon($sisa)
                    : null,
            ] + $tambahan); // field inti di atas tidak bisa ditimpa $tambahan
        });
    }

    /** Teks alasan penolakan plafon; dipakai juga oleh simulasi supaya pesannya sama. */
    public function alasanMelebihiPlafon(float $sisa): string
    {
        return 'Melebihi plafon tahunan. Sisa plafon Rp'.number_format($sisa, 0, ',', '.');
    }
}

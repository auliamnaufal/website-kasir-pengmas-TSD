<?php

namespace App\Services\Angsuran;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Mesin angsuran bersama Simpan Pinjam (P2) dan Kredit Pertokoan (P3).
 * Kontraknya dibekukan di docs/p2-contract.md — jangan ubah signature tanpa
 * mengabari P3.
 *
 * Murni hitungan: tidak membaca config, tidak menyentuh database, dan tidak
 * memvalidasi aturan unit usaha (rentang tenor, plafon). Itu tugas service
 * masing-masing unit, yang memanggil mesin ini dengan angka dari config-nya.
 */
class MesinAngsuran
{
    public const FLAT = 'flat';
    public const EFEKTIF = 'efektif';

    /**
     * @param  float  $bungaPersen  persen per bulan, mis. 1 untuk 1%
     * @param  float  $administrasiPersen  persen dari pokok, dipotong sekali
     * @param  CarbonInterface  $tanggalMulai  angsuran ke-1 jatuh tempo 1 bulan setelah tanggal ini
     * @param  string  $metodeBunga  self::FLAT (dari pokok awal) atau self::EFEKTIF (dari sisa pokok)
     * @param  int|null  $bungaSampaiBulan  bunga hanya dikenakan sampai bulan ke-n; bulan setelahnya
     *                                      bunganya 0. null = sepanjang tenor.
     *
     * @throws InvalidArgumentException
     */
    public function jadwal(
        float $pokok,
        float $bungaPersen,
        int $tenor,
        float $administrasiPersen,
        CarbonInterface $tanggalMulai,
        string $metodeBunga = self::FLAT,
        ?int $bungaSampaiBulan = null,
    ): JadwalAngsuran {
        if ($pokok <= 0) {
            throw new InvalidArgumentException('Pokok harus lebih dari 0.');
        }
        if ($tenor < 1) {
            throw new InvalidArgumentException('Tenor minimal 1 bulan.');
        }
        if ($bungaPersen < 0 || $administrasiPersen < 0) {
            throw new InvalidArgumentException('Bunga dan administrasi tidak boleh negatif.');
        }
        if (! in_array($metodeBunga, [self::FLAT, self::EFEKTIF], true)) {
            throw new InvalidArgumentException("Metode bunga tidak dikenal: {$metodeBunga}.");
        }

        $mulai = CarbonImmutable::instance($tanggalMulai)->startOfDay();
        $pokokPerBulan = round($pokok / $tenor, 2);
        $sisaPokok = $pokok;

        $rincian = [];
        for ($bulan = 1; $bulan <= $tenor; $bulan++) {
            // Bulan terakhir menyerap selisih pembulatan supaya total pokok tepat sama.
            $pokokBulanIni = $bulan === $tenor ? round($sisaPokok, 2) : $pokokPerBulan;

            $dasarBunga = $metodeBunga === self::FLAT ? $pokok : $sisaPokok;
            $bunga = ($bungaSampaiBulan !== null && $bulan > $bungaSampaiBulan)
                ? 0.0
                : round($dasarBunga * $bungaPersen / 100, 2);

            $sisaPokok = round($sisaPokok - $pokokBulanIni, 2);
            $rincian[] = [$bulan, $mulai->addMonthsNoOverflow($bulan), $pokokBulanIni, $bunga, $sisaPokok];
        }

        // Sisa bunga dihitung mundur setelah semua bunga bulanan diketahui.
        $baris = [];
        $sisaBunga = round(array_sum(array_column($rincian, 3)), 2);
        foreach ($rincian as [$bulan, $jatuhTempo, $pokokBulanIni, $bunga, $sisaPokokSetelah]) {
            $sisaBunga = round($sisaBunga - $bunga, 2);
            $baris[] = new BarisAngsuran($bulan, $jatuhTempo, $pokokBulanIni, $bunga, $sisaPokokSetelah, $sisaBunga);
        }

        return new JadwalAngsuran(
            pokok: round($pokok, 2),
            bungaPersen: $bungaPersen,
            tenor: $tenor,
            metodeBunga: $metodeBunga,
            administrasi: round($pokok * $administrasiPersen / 100, 2),
            tanggalMulai: $mulai,
            baris: $baris,
        );
    }

    /**
     * Rincian pelunasan dipercepat setelah $angsuranTerbayar angsuran dibayar.
     * Yang ditagih: seluruh sisa pokok + bunga bulan berjalan (bulan ke
     * $angsuranTerbayar + 1). Bila pelunasan terjadi sebelum bulan
     * $bulanBungaMinimum, bunga tetap ditagih sampai bulan itu; bunga bulan
     * setelahnya dibebaskan.
     *
     * @throws InvalidArgumentException
     */
    public function pelunasan(JadwalAngsuran $jadwal, int $angsuranTerbayar, int $bulanBungaMinimum = 0): RincianPelunasan
    {
        if ($angsuranTerbayar < 0 || $angsuranTerbayar > $jadwal->tenor) {
            throw new InvalidArgumentException("Angsuran terbayar harus antara 0 dan {$jadwal->tenor}.");
        }

        if ($angsuranTerbayar === $jadwal->tenor) {
            return new RincianPelunasan($angsuranTerbayar, $angsuranTerbayar, 0.0, 0.0, 0.0);
        }

        $bungaSampaiBulan = min($jadwal->tenor, max($angsuranTerbayar + 1, $bulanBungaMinimum));

        $bungaDitagih = 0.0;
        foreach ($jadwal->baris as $baris) {
            if ($baris->bulanKe > $angsuranTerbayar && $baris->bulanKe <= $bungaSampaiBulan) {
                $bungaDitagih += $baris->bunga;
            }
        }
        $bungaDitagih = round($bungaDitagih, 2);

        return new RincianPelunasan(
            angsuranTerbayar: $angsuranTerbayar,
            bungaSampaiBulan: $bungaSampaiBulan,
            sisaPokok: $jadwal->sisaPokokSetelah($angsuranTerbayar),
            bungaDitagih: $bungaDitagih,
            bungaDibebaskan: round($jadwal->sisaBungaSetelah($angsuranTerbayar) - $bungaDitagih, 2),
        );
    }
}

<?php

namespace App\Services\Angsuran;

/** Hasil MesinAngsuran::pelunasan(). Pokok dan bunga sengaja dipisah (issue #1 tugas 6). */
final class RincianPelunasan
{
    public readonly float $total;

    public function __construct(
        public readonly int $angsuranTerbayar,
        // Bunga ditagih untuk bulan ke-($angsuranTerbayar + 1) sampai bulan ini.
        public readonly int $bungaSampaiBulan,
        public readonly float $sisaPokok,
        public readonly float $bungaDitagih,
        public readonly float $bungaDibebaskan,
    ) {
        $this->total = round($sisaPokok + $bungaDitagih, 2);
    }

    public function toArray(): array
    {
        return [
            'angsuran_terbayar' => $this->angsuranTerbayar,
            'bunga_sampai_bulan' => $this->bungaSampaiBulan,
            'sisa_pokok' => $this->sisaPokok,
            'bunga_ditagih' => $this->bungaDitagih,
            'bunga_dibebaskan' => $this->bungaDibebaskan,
            'total' => $this->total,
        ];
    }
}

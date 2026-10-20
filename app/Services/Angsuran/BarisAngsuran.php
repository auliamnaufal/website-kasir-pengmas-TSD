<?php

namespace App\Services\Angsuran;

use Carbon\CarbonImmutable;

/** Satu bulan dalam jadwal angsuran. Semua nominal dalam rupiah, 2 desimal. */
final class BarisAngsuran
{
    public readonly float $total;

    public function __construct(
        public readonly int $bulanKe,
        public readonly CarbonImmutable $jatuhTempo,
        public readonly float $pokok,
        public readonly float $bunga,
        // Sisa pokok dan sisa bunga terjadwal SETELAH angsuran bulan ini dibayar.
        public readonly float $sisaPokok,
        public readonly float $sisaBunga,
    ) {
        $this->total = round($pokok + $bunga, 2);
    }

    public function toArray(): array
    {
        return [
            'bulan_ke' => $this->bulanKe,
            'jatuh_tempo' => $this->jatuhTempo->toDateString(),
            'pokok' => $this->pokok,
            'bunga' => $this->bunga,
            'total' => $this->total,
            'sisa_pokok' => $this->sisaPokok,
            'sisa_bunga' => $this->sisaBunga,
        ];
    }
}

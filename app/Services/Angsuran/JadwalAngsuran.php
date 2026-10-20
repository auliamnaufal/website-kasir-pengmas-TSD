<?php

namespace App\Services\Angsuran;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/** Hasil MesinAngsuran::jadwal(). Immutable. */
final class JadwalAngsuran
{
    /**
     * @param  list<BarisAngsuran>  $baris  urut bulan ke-1 sampai ke-$tenor
     */
    public function __construct(
        public readonly float $pokok,
        public readonly float $bungaPersen,
        public readonly int $tenor,
        public readonly string $metodeBunga,
        public readonly float $administrasi,
        public readonly CarbonImmutable $tanggalMulai,
        public readonly array $baris,
    ) {
    }

    public function totalPokok(): float
    {
        return round(array_sum(array_map(fn (BarisAngsuran $b) => $b->pokok, $this->baris)), 2);
    }

    public function totalBunga(): float
    {
        return round(array_sum(array_map(fn (BarisAngsuran $b) => $b->bunga, $this->baris)), 2);
    }

    public function totalAngsuran(): float
    {
        return round($this->totalPokok() + $this->totalBunga(), 2);
    }

    /** Pokok yang diterima peminjam setelah administrasi dipotong. */
    public function pokokBersih(): float
    {
        return round($this->pokok - $this->administrasi, 2);
    }

    public function sisaPokokSetelah(int $angsuranTerbayar): float
    {
        return $angsuranTerbayar === 0 ? $this->pokok : $this->bulan($angsuranTerbayar)->sisaPokok;
    }

    public function sisaBungaSetelah(int $angsuranTerbayar): float
    {
        return $angsuranTerbayar === 0 ? $this->totalBunga() : $this->bulan($angsuranTerbayar)->sisaBunga;
    }

    public function bulan(int $bulanKe): BarisAngsuran
    {
        return $this->baris[$bulanKe - 1]
            ?? throw new InvalidArgumentException("Bulan ke-{$bulanKe} di luar tenor {$this->tenor}.");
    }

    public function toArray(): array
    {
        return [
            'pokok' => $this->pokok,
            'bunga_persen' => $this->bungaPersen,
            'tenor' => $this->tenor,
            'metode_bunga' => $this->metodeBunga,
            'administrasi' => $this->administrasi,
            'tanggal_mulai' => $this->tanggalMulai->toDateString(),
            'total_pokok' => $this->totalPokok(),
            'total_bunga' => $this->totalBunga(),
            'total_angsuran' => $this->totalAngsuran(),
            'baris' => array_map(fn (BarisAngsuran $b) => $b->toArray(), $this->baris),
        ];
    }
}

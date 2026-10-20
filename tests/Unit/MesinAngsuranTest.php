<?php

namespace Tests\Unit;

use App\Services\Angsuran\MesinAngsuran;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MesinAngsuranTest extends TestCase
{
    private function mesin(): MesinAngsuran
    {
        return new MesinAngsuran();
    }

    public function test_flat_bunga_dari_pokok_awal_setiap_bulan(): void
    {
        $jadwal = $this->mesin()->jadwal(10000000, 1, 10, 3, CarbonImmutable::parse('2026-01-10'));

        $this->assertCount(10, $jadwal->baris);
        foreach ($jadwal->baris as $baris) {
            $this->assertSame(1000000.0, $baris->pokok);
            $this->assertSame(100000.0, $baris->bunga);
            $this->assertSame(1100000.0, $baris->total);
        }
        $this->assertSame(10000000.0, $jadwal->totalPokok());
        $this->assertSame(1000000.0, $jadwal->totalBunga());
        $this->assertSame(11000000.0, $jadwal->totalAngsuran());
        $this->assertSame(300000.0, $jadwal->administrasi);
        $this->assertSame(9700000.0, $jadwal->pokokBersih());
    }

    public function test_sisa_pokok_dan_sisa_bunga_per_bulan(): void
    {
        $jadwal = $this->mesin()->jadwal(10000000, 1, 10, 3, CarbonImmutable::parse('2026-01-10'));

        $this->assertSame(9000000.0, $jadwal->bulan(1)->sisaPokok);
        $this->assertSame(900000.0, $jadwal->bulan(1)->sisaBunga);
        $this->assertSame(0.0, $jadwal->bulan(10)->sisaPokok);
        $this->assertSame(0.0, $jadwal->bulan(10)->sisaBunga);
        $this->assertSame(10000000.0, $jadwal->sisaPokokSetelah(0));
        $this->assertSame(1000000.0, $jadwal->sisaBungaSetelah(0));
    }

    public function test_jatuh_tempo_sebulan_setelah_mulai_tanpa_overflow_akhir_bulan(): void
    {
        $jadwal = $this->mesin()->jadwal(1000000, 1, 12, 0, CarbonImmutable::parse('2026-01-31'));

        $this->assertSame('2026-02-28', $jadwal->bulan(1)->jatuhTempo->toDateString());
        $this->assertSame('2026-03-31', $jadwal->bulan(2)->jatuhTempo->toDateString());
        $this->assertSame('2027-01-31', $jadwal->bulan(12)->jatuhTempo->toDateString());
    }

    public function test_selisih_pembulatan_pokok_diserap_bulan_terakhir(): void
    {
        $jadwal = $this->mesin()->jadwal(1000000, 0, 3, 0, CarbonImmutable::parse('2026-01-01'));

        $this->assertSame(333333.33, $jadwal->bulan(1)->pokok);
        $this->assertSame(333333.33, $jadwal->bulan(2)->pokok);
        $this->assertSame(333333.34, $jadwal->bulan(3)->pokok);
        $this->assertSame(1000000.0, $jadwal->totalPokok());
        $this->assertSame(0.0, $jadwal->bulan(3)->sisaPokok);
    }

    public function test_efektif_bunga_menurun_mengikuti_sisa_pokok(): void
    {
        $jadwal = $this->mesin()->jadwal(12000000, 1, 12, 0, CarbonImmutable::parse('2026-01-01'), MesinAngsuran::EFEKTIF);

        $this->assertSame(120000.0, $jadwal->bulan(1)->bunga);
        $this->assertSame(110000.0, $jadwal->bulan(2)->bunga);
        $this->assertSame(10000.0, $jadwal->bulan(12)->bunga);
        // 1% x 1 jt x (12 + 11 + ... + 1)
        $this->assertSame(780000.0, $jadwal->totalBunga());
    }

    public function test_bunga_sampai_bulan_membebaskan_bunga_bulan_setelahnya(): void
    {
        // Pola kredit barang P3: tenor 12, bunga hanya sampai bulan ke-11.
        $jadwal = $this->mesin()->jadwal(1200000, 1, 12, 2, CarbonImmutable::parse('2026-01-01'), MesinAngsuran::FLAT, 11);

        $this->assertSame(12000.0, $jadwal->bulan(11)->bunga);
        $this->assertSame(0.0, $jadwal->bulan(12)->bunga);
        $this->assertSame(132000.0, $jadwal->totalBunga());
        $this->assertSame(24000.0, $jadwal->administrasi);
    }

    public function test_pelunasan_sebelum_bulan_minimum_tetap_ditagih_bunga_sampai_bulan_minimum(): void
    {
        $mesin = $this->mesin();
        $jadwal = $mesin->jadwal(10000000, 1, 20, 3, CarbonImmutable::parse('2026-01-01'));

        $rincian = $mesin->pelunasan($jadwal, 3, 10);

        $this->assertSame(8500000.0, $rincian->sisaPokok);
        $this->assertSame(10, $rincian->bungaSampaiBulan);
        $this->assertSame(700000.0, $rincian->bungaDitagih); // bulan 4..10
        $this->assertSame(1000000.0, $rincian->bungaDibebaskan); // bulan 11..20
        $this->assertSame(9200000.0, $rincian->total);
    }

    public function test_pelunasan_setelah_bulan_minimum_hanya_bunga_bulan_berjalan(): void
    {
        $mesin = $this->mesin();
        $jadwal = $mesin->jadwal(10000000, 1, 20, 3, CarbonImmutable::parse('2026-01-01'));

        $rincian = $mesin->pelunasan($jadwal, 12, 10);

        $this->assertSame(13, $rincian->bungaSampaiBulan);
        $this->assertSame(4000000.0, $rincian->sisaPokok);
        $this->assertSame(100000.0, $rincian->bungaDitagih);
        $this->assertSame(700000.0, $rincian->bungaDibebaskan);
    }

    public function test_bulan_minimum_tidak_melewati_tenor(): void
    {
        $mesin = $this->mesin();
        $jadwal = $mesin->jadwal(1200000, 1, 12, 0, CarbonImmutable::parse('2026-01-01'));

        $rincian = $mesin->pelunasan($jadwal, 0, 20);

        $this->assertSame(12, $rincian->bungaSampaiBulan);
        $this->assertSame(144000.0, $rincian->bungaDitagih);
        $this->assertSame(0.0, $rincian->bungaDibebaskan);
    }

    public function test_pelunasan_saat_semua_angsuran_terbayar_nol(): void
    {
        $mesin = $this->mesin();
        $jadwal = $mesin->jadwal(1000000, 1, 10, 0, CarbonImmutable::parse('2026-01-01'));

        $rincian = $mesin->pelunasan($jadwal, 10, 10);

        $this->assertSame(0.0, $rincian->total);
        $this->assertSame(0.0, $rincian->bungaDibebaskan);
    }

    public function test_input_tidak_valid_ditolak(): void
    {
        $mulai = CarbonImmutable::parse('2026-01-01');
        $cases = [
            fn () => $this->mesin()->jadwal(0, 1, 10, 3, $mulai),
            fn () => $this->mesin()->jadwal(1000, 1, 0, 3, $mulai),
            fn () => $this->mesin()->jadwal(1000, -1, 10, 3, $mulai),
            fn () => $this->mesin()->jadwal(1000, 1, 10, 3, $mulai, 'anuitas'),
            fn () => $this->mesin()->pelunasan($this->mesin()->jadwal(1000, 1, 10, 3, $mulai), 11),
        ];

        foreach ($cases as $i => $case) {
            try {
                $case();
                $this->fail("Kasus #{$i} seharusnya ditolak.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}

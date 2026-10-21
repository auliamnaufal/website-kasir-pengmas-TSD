<?php

namespace App\Services;

use App\Models\KreditAngsuran;
use App\Models\KreditBarang;
use App\Models\Piutang;
use App\Models\User;
use App\Services\Angsuran\JadwalAngsuran;
use App\Services\Angsuran\MesinAngsuran;
use App\Services\Angsuran\RincianPelunasan;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Aturan Unit Kredit Pertokoan (issue #2) di atas MesinAngsuran P2: pengajuan
 * dan jadwal, potong gaji lewat piutang P1, pembayaran angsuran, pelunasan
 * dipercepat, dan laporan bulanan. Plafon tahunan ada di KreditPlafonService.
 *
 * Administrasi (2%) dicatat sebagai pendapatan koperasi sekali per kredit dan
 * TIDAK ditambahkan ke angsuran: barang diterima utuh, jadi tidak ada dana
 * yang bisa dipotong seperti pada pinjaman tunai. Open question klien.
 */
class KreditService
{
    public function __construct(
        private readonly MesinAngsuran $mesin,
        private readonly KreditPlafonService $plafon,
    ) {
    }

    /** Bunga hanya sampai bulan ini (null = sepanjang tenor), dari config. */
    private function bungaSampaiBulanConfig(): ?int
    {
        $nilai = config('kredit.bunga_sampai_bulan');

        return ($nilai === null || $nilai === '') ? null : (int) $nilai;
    }

    /** Jadwal kredit tersimpan, dihitung ulang dari parameter yang dibekukan saat pengajuan. */
    public function jadwalKredit(KreditBarang $kredit): JadwalAngsuran
    {
        $pokok = (float) $kredit->pokok;

        return $this->mesin->jadwal(
            $pokok,
            (float) $kredit->bunga_persen,
            (int) $kredit->tenor,
            $pokok > 0 ? (float) $kredit->administrasi / $pokok * 100 : 0,
            $kredit->tanggal_mulai ?? $kredit->tanggal_pengajuan,
            $kredit->metode_bunga,
            $kredit->bunga_sampai_bulan,
        );
    }

    /** Berapa yang harus dibayar bila kredit aktif ini dilunasi sekarang. */
    public function rincianPelunasan(KreditBarang $kredit): RincianPelunasan
    {
        $kredit->loadMissing('angsuran');

        return $this->mesin->pelunasan(
            $this->jadwalKredit($kredit),
            $kredit->angsuranTerbayar(),
            (int) $kredit->bulan_bunga_minimum,
        );
    }

    /**
     * Hitung kredit baru tanpa menyimpan apa pun. Dipakai halaman simulasi;
     * aturan penolakannya sama dengan ajukan() (plafon, lalu kecukupan gaji).
     *
     * @return array{
     *     jadwal: JadwalAngsuran,
     *     administrasi: float,
     *     plafon: float,
     *     plafon_terpakai: float,
     *     sisa_plafon: float,
     *     alasan_penolakan: ?string,
     * }
     *
     * @throws InvalidArgumentException bila pokok, tenor, atau metode bayar tidak valid
     */
    public function simulasi(User $anggota, float $pokok, int $tenor, string $metodeBayar, CarbonInterface $tanggal): array
    {
        $this->validasi($pokok, $tenor, $metodeBayar);

        $jadwal = $this->mesin->jadwal(
            $pokok,
            (float) config('kredit.bunga_persen'),
            $tenor,
            (float) config('kredit.administrasi_persen'),
            $tanggal,
            config('kredit.metode_bunga'),
            $this->bungaSampaiBulanConfig(),
        );

        $sisa = $this->plafon->sisa($anggota, $tanggal);

        $alasan = null;
        if ($pokok > $sisa) {
            $alasan = $this->plafon->alasanMelebihiPlafon($sisa);
        } elseif ($metodeBayar === 'potong_gaji') {
            $alasan = $this->cekKecukupanGaji($anggota, $jadwal);
        }

        return [
            'jadwal' => $jadwal,
            'administrasi' => $jadwal->administrasi,
            'plafon' => $this->plafon->plafonTahunan(),
            'plafon_terpakai' => $this->plafon->terpakai($anggota, $tanggal),
            'sisa_plafon' => $sisa,
            'alasan_penolakan' => $alasan,
        ];
    }

    /**
     * Simpan pengajuan kredit. Otomatis ditolak (status `ditolak`, tanpa
     * jadwal) bila melewati plafon tahunan atau, untuk potong gaji, melewati
     * batas potongan gaji. Bila disetujui: jadwal angsuran disimpan dan untuk
     * potong gaji setiap angsuran dicatat sebagai piutang di periode jatuh
     * temponya.
     *
     * @throws InvalidArgumentException
     */
    public function ajukan(
        User $anggota,
        string $namaBarang,
        float $pokok,
        int $tenor,
        string $metodeBayar,
        CarbonInterface $tanggal,
        ?User $oleh = null,
    ): KreditBarang {
        $this->validasi($pokok, $tenor, $metodeBayar);

        return DB::transaction(function () use ($anggota, $namaBarang, $pokok, $tenor, $metodeBayar, $tanggal, $oleh) {
            // Plafon diputuskan dulu (dengan kunci baris anggota). Parameter jadwal
            // ikut dibekukan di baris kredit, juga untuk kredit yang ditolak.
            $kredit = $this->plafon->ajukan($anggota, $namaBarang, $pokok, $tenor, $tanggal, [
                'metode_bayar' => $metodeBayar,
                'metode_bunga' => config('kredit.metode_bunga'),
                'bunga_sampai_bulan' => $this->bungaSampaiBulanConfig(),
                'bulan_bunga_minimum' => (int) config('kredit.bulan_bunga_minimum'),
                'tanggal_mulai' => $tanggal->toDateString(),
                'dibuat_oleh' => $oleh?->user_id,
            ]);

            if ($kredit->status === 'ditolak') {
                $kredit->update(['tanggal_mulai' => null]);

                return $kredit;
            }

            $jadwal = $this->jadwalKredit($kredit);

            if ($metodeBayar === 'potong_gaji' && ($alasan = $this->cekKecukupanGaji($anggota, $jadwal))) {
                // Ditolak sesudah lolos plafon: statusnya jadi `ditolak`, sehingga tidak memakai plafon.
                $kredit->update(['status' => 'ditolak', 'alasan_penolakan' => $alasan, 'tanggal_mulai' => null]);

                return $kredit;
            }

            foreach ($jadwal->baris as $baris) {
                $angsuran = $kredit->angsuran()->create([
                    'bulan_ke' => $baris->bulanKe,
                    'jatuh_tempo' => $baris->jatuhTempo->toDateString(),
                    'pokok' => $baris->pokok,
                    'bunga' => $baris->bunga,
                    'sisa_pokok' => $baris->sisaPokok,
                    'sisa_bunga' => $baris->sisaBunga,
                ]);

                if ($metodeBayar === 'potong_gaji') {
                    Piutang::create([
                        'user_id' => $anggota->user_id,
                        'source_type' => 'kredit',
                        'source_id' => $angsuran->id,
                        'amount' => $baris->total,
                        'status' => 'belum_lunas',
                        'period' => Piutang::periodFor($baris->jatuhTempo),
                    ]);
                }
            }

            return $kredit->load('angsuran');
        });
    }

    /**
     * Bayar satu angsuran. Harus urut: angsuran berikutnya yang belum dibayar.
     * Piutang potong gaji angsuran ini ikut ditandai lunas. Kredit menjadi
     * lunas saat angsuran terakhir dibayar.
     *
     * @throws ValidationException
     */
    public function bayarAngsuran(KreditAngsuran $angsuran, ?User $oleh = null): KreditBarang
    {
        return DB::transaction(function () use ($angsuran, $oleh) {
            $kredit = KreditBarang::whereKey($angsuran->kredit_barang_id)->lockForUpdate()->firstOrFail();

            if ($kredit->status !== 'disetujui') {
                throw ValidationException::withMessages(['angsuran' => 'Kredit ini sudah tidak aktif.']);
            }

            $berikutnya = $kredit->angsuran()->where('status', 'belum_lunas')->first();
            if ($berikutnya === null || $berikutnya->id !== $angsuran->id) {
                throw ValidationException::withMessages([
                    'angsuran' => $berikutnya === null
                        ? 'Angsuran ini sudah dibayar.'
                        : "Bayar angsuran ke-{$berikutnya->bulan_ke} terlebih dahulu.",
                ]);
            }

            $berikutnya->update(['status' => 'lunas', 'dibayar_pada' => now(), 'dibayar_oleh' => $oleh?->user_id]);

            Piutang::where('source_type', 'kredit')
                ->where('source_id', $berikutnya->id)
                ->where('status', 'belum_lunas')
                ->update(['status' => 'lunas', 'settled_at' => now(), 'settled_by' => $oleh?->user_id]);

            if (! $kredit->angsuran()->where('status', 'belum_lunas')->exists()) {
                $kredit->update([
                    'status' => 'lunas',
                    'pelunasan_pokok' => $kredit->angsuran()->sum('pokok'),
                    'pelunasan_bunga' => $kredit->angsuran()->sum('bunga'),
                    'dilunasi_pada' => now(),
                ]);
            }

            return $kredit->fresh('angsuran');
        });
    }

    /**
     * Pelunasan dipercepat, dibayar langsung (bukan potong gaji). Pokok dan
     * bunga dicatat terpisah. Lihat MesinAngsuran::pelunasan() untuk aturan
     * bunga minimum.
     *
     * @throws ValidationException
     */
    public function lunasiDipercepat(KreditBarang $kredit, ?User $oleh = null): RincianPelunasan
    {
        return DB::transaction(function () use ($kredit, $oleh) {
            $kredit = KreditBarang::whereKey($kredit->id)->lockForUpdate()->firstOrFail();

            if ($kredit->status !== 'disetujui') {
                throw ValidationException::withMessages(['kredit' => 'Kredit ini sudah tidak aktif.']);
            }

            $rincian = $this->rincianPelunasan($kredit);

            // Piutang potong gaji yang belum jatuh tempo dihapus: tidak akan dipotong lagi.
            $sisaId = $kredit->angsuran()->where('status', 'belum_lunas')->pluck('id');
            Piutang::where('source_type', 'kredit')
                ->whereIn('source_id', $sisaId)
                ->where('status', 'belum_lunas')
                ->delete();

            KreditAngsuran::whereIn('id', $sisaId)->update([
                'status' => 'pelunasan',
                'dibayar_pada' => now(),
                'dibayar_oleh' => $oleh?->user_id,
            ]);

            $terbayar = $kredit->angsuran()->where('status', 'lunas')->get();

            $kredit->update([
                'status' => 'lunas',
                'pelunasan_pokok' => round((float) $terbayar->sum('pokok') + $rincian->sisaPokok, 2),
                'pelunasan_bunga' => round((float) $terbayar->sum('bunga') + $rincian->bungaDitagih, 2),
                'dilunasi_pada' => now(),
            ]);

            return $rincian;
        });
    }

    /**
     * Laporan bulan kalender $bulan ("YYYY-MM"): angsuran yang jatuh tempo di
     * bulan itu beserta sisa pokok/bunga per anggota, kredit yang lunas di
     * bulan itu (pokok dan bunga terpisah), dan pendapatan administrasi.
     */
    public function laporanBulanan(string $bulan): array
    {
        $awal = Carbon::createFromFormat('!Y-m', $bulan)->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();

        // Angsuran `pelunasan` sudah dibayar lewat pelunasan dipercepat, jadi tidak
        // lagi jatuh tempo di bulannya.
        $angsuran = KreditAngsuran::with('kredit.user')
            ->whereBetween('jatuh_tempo', [$awal->toDateString(), $akhir->toDateString()])
            ->where('status', '!=', 'pelunasan')
            ->whereHas('kredit', fn ($q) => $q->where('status', '!=', 'ditolak'))
            ->orderBy('jatuh_tempo')
            ->get();

        $perAnggota = $angsuran->groupBy(fn ($a) => $a->kredit->user_id)->map(fn ($baris) => [
            'anggota' => $baris->first()->kredit->user,
            'jumlah_kredit' => $baris->count(),
            'pokok' => round((float) $baris->sum('pokok'), 2),
            'bunga' => round((float) $baris->sum('bunga'), 2),
            'total' => round((float) $baris->sum(fn ($a) => $a->total()), 2),
            'sisa_pokok' => round((float) $baris->sum('sisa_pokok'), 2),
            'sisa_bunga' => round((float) $baris->sum('sisa_bunga'), 2),
            'sudah_dibayar' => $baris->every(fn ($a) => $a->status !== 'belum_lunas'),
        ])->values();

        $lunas = KreditBarang::with('user')
            ->where('status', 'lunas')
            ->whereBetween('dilunasi_pada', [$awal, $akhir])
            ->orderBy('dilunasi_pada')
            ->get();

        return [
            'bulan' => $awal,
            'angsuran' => $angsuran,
            'per_anggota' => $perAnggota,
            'total_pokok' => round((float) $angsuran->sum('pokok'), 2),
            'total_bunga' => round((float) $angsuran->sum('bunga'), 2),
            'lunas' => $lunas,
            'lunas_pokok' => round((float) $lunas->sum('pelunasan_pokok'), 2),
            'lunas_bunga' => round((float) $lunas->sum('pelunasan_bunga'), 2),
            'pendapatan_administrasi' => round((float) KreditBarang::whereIn('status', ['disetujui', 'lunas'])
                ->whereBetween('tanggal_pengajuan', [$awal->toDateString(), $akhir->toDateString()])
                ->sum('administrasi'), 2),
        ];
    }

    /** @throws InvalidArgumentException */
    private function validasi(float $pokok, int $tenor, string $metodeBayar): void
    {
        if ($pokok <= 0) {
            throw new InvalidArgumentException('Pokok kredit harus lebih dari 0.');
        }

        $tenorMin = (int) config('kredit.tenor_min');
        $tenorMax = (int) config('kredit.tenor_max');
        if ($tenor < $tenorMin || $tenor > $tenorMax) {
            throw new InvalidArgumentException("Tenor harus antara {$tenorMin} dan {$tenorMax} bulan.");
        }

        if (! in_array($metodeBayar, ['potong_gaji', 'bayar_sendiri'], true)) {
            throw new InvalidArgumentException("Metode bayar tidak dikenal: {$metodeBayar}.");
        }
    }

    /**
     * Potong gaji hanya boleh bila setiap angsuran, ditambah piutang lain di
     * periode jatuh temponya, masih di bawah batas max_potongan_persen x gaji.
     * Gaji/jabatan yang belum dikonfigurasi ditolak (fail closed, sama dengan
     * Piutang::wouldExceedLimit di kasir dan PinjamanService P2).
     */
    private function cekKecukupanGaji(User $anggota, JadwalAngsuran $jadwal): ?string
    {
        $config = $anggota->jabatanConfig;
        if ($anggota->gaji === null || $config === null) {
            return 'Gaji atau jabatan anggota belum dikonfigurasi, tidak bisa potong gaji.';
        }

        $batas = (float) $anggota->gaji * (float) $config->max_potongan_persen / 100;

        $perPeriode = [];
        foreach ($jadwal->baris as $baris) {
            $periode = Piutang::periodFor($baris->jatuhTempo);
            $perPeriode[$periode] = ($perPeriode[$periode] ?? 0) + $baris->total;
        }

        foreach ($perPeriode as $periode => $angsuran) {
            $piutangLain = (float) Piutang::where('user_id', $anggota->user_id)
                ->where('status', 'belum_lunas')
                ->where('period', $periode)
                ->sum('amount');

            if ($piutangLain + $angsuran > $batas) {
                return "Potongan gaji periode {$periode} melebihi batas Rp".number_format($batas, 0, ',', '.').'.';
            }
        }

        return null;
    }
}

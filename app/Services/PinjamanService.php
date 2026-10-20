<?php

namespace App\Services;

use App\Models\Pinjaman;
use App\Models\PinjamanAngsuran;
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
 * Aturan Unit Simpan Pinjam (issue #1) di atas MesinAngsuran: plafon,
 * administrasi 1% + 2%, kompensasi pinjaman lama, potong gaji, pembayaran
 * angsuran, pelunasan dipercepat, dan laporan bulanan.
 */
class PinjamanService
{
    public function __construct(
        private readonly MesinAngsuran $mesin,
        private readonly SimpananService $simpanan,
    ) {
    }

    public function plafon(): float
    {
        return (float) config('simpan_pinjam.plafon');
    }

    /**
     * Angka yang memakai plafon anggota, sesuai config simpan_pinjam.plafon_basis.
     * $kecualiPinjamanId: pinjaman aktif yang akan ditutup kompensasi, jadi tidak
     * dihitung lagi (hanya berlaku untuk basis outstanding).
     */
    public function plafonTerpakai(User $anggota, CarbonInterface $tanggal, array $kecualiPinjamanId = []): float
    {
        if (config('simpan_pinjam.plafon_basis') === 'tahunan') {
            return round((float) Pinjaman::where('user_id', $anggota->user_id)
                ->whereIn('status', ['aktif', 'lunas'])
                ->whereBetween('tanggal_pengajuan', [
                    $tanggal->copy()->startOfYear()->toDateString(),
                    $tanggal->copy()->endOfYear()->toDateString(),
                ])
                ->sum('pokok'), 2);
        }

        return round((float) PinjamanAngsuran::where('status', 'belum_lunas')
            ->whereHas('pinjaman', fn ($q) => $q->where('user_id', $anggota->user_id)
                ->where('status', 'aktif')
                ->whereNotIn('id', $kecualiPinjamanId))
            ->sum('pokok'), 2);
    }

    public function sisaPlafon(User $anggota, CarbonInterface $tanggal, array $kecualiPinjamanId = []): float
    {
        return max(0.0, round($this->plafon() - $this->plafonTerpakai($anggota, $tanggal, $kecualiPinjamanId), 2));
    }

    /** Jadwal pinjaman tersimpan, dihitung ulang dari parameter yang dibekukan saat pengajuan. */
    public function jadwalPinjaman(Pinjaman $pinjaman): JadwalAngsuran
    {
        $pokok = (float) $pinjaman->pokok;

        return $this->mesin->jadwal(
            $pokok,
            (float) $pinjaman->bunga_persen,
            (int) $pinjaman->tenor,
            $pokok > 0 ? (float) $pinjaman->administrasi / $pokok * 100 : 0,
            $pinjaman->tanggal_mulai ?? $pinjaman->tanggal_pengajuan,
            $pinjaman->metode_bunga,
        );
    }

    /** Berapa yang harus dibayar bila pinjaman aktif ini dilunasi sekarang. */
    public function rincianPelunasan(Pinjaman $pinjaman): RincianPelunasan
    {
        $pinjaman->loadMissing('angsuran');

        return $this->mesin->pelunasan(
            $this->jadwalPinjaman($pinjaman),
            $pinjaman->angsuranTerbayar(),
            (int) $pinjaman->bulan_bunga_minimum,
        );
    }

    /**
     * Hitung pinjaman baru tanpa menyimpan apa pun. Dipakai halaman simulasi
     * dan oleh ajukan(), jadi hasil simulasi selalu sama dengan hasil pengajuan.
     *
     * @return array{
     *     jadwal: JadwalAngsuran,
     *     administrasi_pendapatan: float,
     *     administrasi_sukarela: float,
     *     kompensasi: list<array{pinjaman: Pinjaman, rincian: RincianPelunasan}>,
     *     total_kompensasi: float,
     *     dana_diterima: float,
     *     plafon: float,
     *     plafon_terpakai: float,
     *     sisa_plafon: float,
     *     alasan_penolakan: ?string,
     * }
     *
     * @throws InvalidArgumentException bila pokok, tenor, atau metode bayar tidak valid
     */
    public function simulasi(
        User $anggota,
        float $pokok,
        int $tenor,
        string $metodeBayar,
        CarbonInterface $tanggal,
        bool $kompensasi = false,
    ): array {
        $tenorMin = (int) config('simpan_pinjam.tenor_min');
        $tenorMax = (int) config('simpan_pinjam.tenor_max');
        if ($tenor < $tenorMin || $tenor > $tenorMax) {
            throw new InvalidArgumentException("Tenor harus antara {$tenorMin} dan {$tenorMax} bulan.");
        }
        if (! in_array($metodeBayar, ['potong_gaji', 'bayar_sendiri'], true)) {
            throw new InvalidArgumentException("Metode bayar tidak dikenal: {$metodeBayar}.");
        }

        $persenPendapatan = (float) config('simpan_pinjam.administrasi_pendapatan_persen');
        $persenSukarela = (float) config('simpan_pinjam.administrasi_sukarela_persen');

        $jadwal = $this->mesin->jadwal(
            $pokok,
            (float) config('simpan_pinjam.bunga_persen'),
            $tenor,
            $persenPendapatan + $persenSukarela,
            $tanggal,
            config('simpan_pinjam.metode_bunga'),
        );
        $administrasiPendapatan = round($pokok * $persenPendapatan / 100, 2);

        $daftarKompensasi = [];
        if ($kompensasi) {
            $aktif = Pinjaman::where('user_id', $anggota->user_id)->where('status', 'aktif')->with('angsuran')->get();
            foreach ($aktif as $lama) {
                $daftarKompensasi[] = ['pinjaman' => $lama, 'rincian' => $this->rincianPelunasan($lama)];
            }
        }
        $totalKompensasi = round(array_sum(array_map(fn ($k) => $k['rincian']->total, $daftarKompensasi)), 2);
        $kecualiPinjaman = array_map(fn ($k) => $k['pinjaman']->id, $daftarKompensasi);

        $terpakai = $this->plafonTerpakai($anggota, $tanggal, $kecualiPinjaman);
        $danaDiterima = round($jadwal->pokokBersih() - $totalKompensasi, 2);

        $alasan = null;
        if ($terpakai + $pokok > $this->plafon()) {
            $alasan = 'Melebihi plafon pinjaman Rp'.$this->rupiah($this->plafon())
                .'. Sisa plafon Rp'.$this->rupiah(max(0, $this->plafon() - $terpakai)).'.';
        } elseif ($danaDiterima < 0) {
            $alasan = 'Pinjaman baru tidak cukup untuk menutup kompensasi pinjaman lama sebesar Rp'
                .$this->rupiah($totalKompensasi).'.';
        } elseif ($metodeBayar === 'potong_gaji') {
            $kecualiAngsuran = PinjamanAngsuran::whereIn('pinjaman_id', $kecualiPinjaman)->pluck('id')->all();
            $alasan = $this->cekKecukupanGaji($anggota, $jadwal, $kecualiAngsuran);
        }

        return [
            'jadwal' => $jadwal,
            'administrasi_pendapatan' => $administrasiPendapatan,
            'administrasi_sukarela' => round($jadwal->administrasi - $administrasiPendapatan, 2),
            'kompensasi' => $daftarKompensasi,
            'total_kompensasi' => $totalKompensasi,
            'dana_diterima' => $danaDiterima,
            'plafon' => $this->plafon(),
            'plafon_terpakai' => $terpakai,
            'sisa_plafon' => max(0.0, round($this->plafon() - $terpakai, 2)),
            'alasan_penolakan' => $alasan,
        ];
    }

    /**
     * Simpan pengajuan pinjaman. Otomatis ditolak (status `ditolak`, tanpa
     * jadwal) bila simulasi memberi alasan penolakan. Bila disetujui: jadwal
     * angsuran disimpan, 2% administrasi masuk simpanan sukarela, pinjaman lama
     * ditutup bila $kompensasi, dan untuk potong gaji setiap angsuran dicatat
     * sebagai piutang di periode jatuh temponya.
     *
     * @throws InvalidArgumentException
     */
    public function ajukan(
        User $anggota,
        float $pokok,
        int $tenor,
        string $metodeBayar,
        CarbonInterface $tanggal,
        bool $kompensasi = false,
        ?User $oleh = null,
    ): Pinjaman {
        return DB::transaction(function () use ($anggota, $pokok, $tenor, $metodeBayar, $tanggal, $kompensasi, $oleh) {
            // Kunci baris anggota supaya dua pengajuan bersamaan tidak sama-sama
            // lolos membaca sisa plafon yang sama.
            User::where('user_id', $anggota->user_id)->lockForUpdate()->first();

            $hasil = $this->simulasi($anggota, $pokok, $tenor, $metodeBayar, $tanggal, $kompensasi);
            $jadwal = $hasil['jadwal'];
            $ditolak = $hasil['alasan_penolakan'] !== null;

            $pinjaman = Pinjaman::create([
                'user_id' => $anggota->user_id,
                'pokok' => $jadwal->pokok,
                'tenor' => $tenor,
                'bunga_persen' => $jadwal->bungaPersen,
                'metode_bunga' => $jadwal->metodeBunga,
                'bulan_bunga_minimum' => (int) config('simpan_pinjam.bulan_bunga_minimum'),
                'administrasi' => $jadwal->administrasi,
                'administrasi_pendapatan' => $hasil['administrasi_pendapatan'],
                'administrasi_sukarela' => $hasil['administrasi_sukarela'],
                'kompensasi' => $hasil['total_kompensasi'],
                'dana_diterima' => $hasil['dana_diterima'],
                'metode_bayar' => $metodeBayar,
                'tanggal_pengajuan' => $tanggal->toDateString(),
                'tanggal_mulai' => $ditolak ? null : $jadwal->tanggalMulai->toDateString(),
                'status' => $ditolak ? 'ditolak' : 'aktif',
                'alasan_penolakan' => $hasil['alasan_penolakan'],
                'dibuat_oleh' => $oleh?->user_id,
            ]);

            if ($ditolak) {
                return $pinjaman;
            }

            foreach ($hasil['kompensasi'] as $k) {
                $this->tutupDenganPelunasan($k['pinjaman'], $k['rincian'], $oleh, $pinjaman->id);
            }

            foreach ($jadwal->baris as $baris) {
                $angsuran = $pinjaman->angsuran()->create([
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
                        'source_type' => 'pinjaman',
                        'source_id' => $angsuran->id,
                        'amount' => $baris->total,
                        'status' => 'belum_lunas',
                        'period' => Piutang::periodFor($baris->jatuhTempo),
                    ]);
                }
            }

            if ($hasil['administrasi_sukarela'] > 0) {
                $this->simpanan->setorSukarela(
                    $anggota,
                    $hasil['administrasi_sukarela'],
                    $tanggal,
                    'Bagian administrasi pinjaman '.$pinjaman->nomor(),
                    $oleh,
                    'pinjaman',
                    $pinjaman->id,
                );
            }

            return $pinjaman->load('angsuran');
        });
    }

    /**
     * Bayar satu angsuran. Harus urut: angsuran berikutnya yang belum dibayar.
     * Piutang potong gaji angsuran ini ikut ditandai lunas. Pinjaman menjadi
     * lunas saat angsuran terakhir dibayar.
     *
     * @throws ValidationException
     */
    public function bayarAngsuran(PinjamanAngsuran $angsuran, ?User $oleh = null): Pinjaman
    {
        return DB::transaction(function () use ($angsuran, $oleh) {
            $pinjaman = Pinjaman::whereKey($angsuran->pinjaman_id)->lockForUpdate()->firstOrFail();

            if ($pinjaman->status !== 'aktif') {
                throw ValidationException::withMessages(['angsuran' => 'Pinjaman ini sudah tidak aktif.']);
            }

            $berikutnya = $pinjaman->angsuran()->where('status', 'belum_lunas')->first();
            if ($berikutnya === null || $berikutnya->id !== $angsuran->id) {
                throw ValidationException::withMessages([
                    'angsuran' => $berikutnya === null
                        ? 'Angsuran ini sudah dibayar.'
                        : "Bayar angsuran ke-{$berikutnya->bulan_ke} terlebih dahulu.",
                ]);
            }

            $berikutnya->update(['status' => 'lunas', 'dibayar_pada' => now(), 'dibayar_oleh' => $oleh?->user_id]);

            Piutang::where('source_type', 'pinjaman')
                ->where('source_id', $berikutnya->id)
                ->where('status', 'belum_lunas')
                ->update(['status' => 'lunas', 'settled_at' => now(), 'settled_by' => $oleh?->user_id]);

            if (! $pinjaman->angsuran()->where('status', 'belum_lunas')->exists()) {
                $pinjaman->update([
                    'status' => 'lunas',
                    'pelunasan_pokok' => $pinjaman->angsuran()->sum('pokok'),
                    'pelunasan_bunga' => $pinjaman->angsuran()->sum('bunga'),
                    'dilunasi_pada' => now(),
                ]);
            }

            return $pinjaman->fresh('angsuran');
        });
    }

    /**
     * Pelunasan dipercepat, dibayar langsung (bukan potong gaji). Lihat
     * MesinAngsuran::pelunasan() untuk aturan bunga minimum.
     *
     * @throws ValidationException
     */
    public function lunasiDipercepat(Pinjaman $pinjaman, ?User $oleh = null): RincianPelunasan
    {
        return DB::transaction(function () use ($pinjaman, $oleh) {
            $pinjaman = Pinjaman::whereKey($pinjaman->id)->lockForUpdate()->firstOrFail();

            if ($pinjaman->status !== 'aktif') {
                throw ValidationException::withMessages(['pinjaman' => 'Pinjaman ini sudah tidak aktif.']);
            }

            $rincian = $this->rincianPelunasan($pinjaman);
            $this->tutupDenganPelunasan($pinjaman, $rincian, $oleh);

            return $rincian;
        });
    }

    /**
     * Laporan pelunasan bulan kalender $bulan ("YYYY-MM"): angsuran yang jatuh
     * tempo di bulan itu beserta sisa pokok/bunga per anggota, pinjaman yang
     * lunas di bulan itu, dan pendapatan administrasi.
     */
    public function laporanBulanan(string $bulan): array
    {
        $awal = Carbon::createFromFormat('!Y-m', $bulan)->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();

        // Angsuran berstatus `pelunasan` sudah dibayar lewat pelunasan dipercepat /
        // kompensasi, jadi tidak lagi jatuh tempo di bulannya.
        $angsuran = PinjamanAngsuran::with('pinjaman.user')
            ->whereBetween('jatuh_tempo', [$awal->toDateString(), $akhir->toDateString()])
            ->where('status', '!=', 'pelunasan')
            ->whereHas('pinjaman', fn ($q) => $q->where('status', '!=', 'ditolak'))
            ->orderBy('jatuh_tempo')
            ->get();

        $perAnggota = $angsuran->groupBy(fn ($a) => $a->pinjaman->user_id)->map(fn ($baris) => [
            'anggota' => $baris->first()->pinjaman->user,
            'jumlah_pinjaman' => $baris->count(),
            'pokok' => round((float) $baris->sum('pokok'), 2),
            'bunga' => round((float) $baris->sum('bunga'), 2),
            'total' => round((float) $baris->sum(fn ($a) => $a->total()), 2),
            'sisa_pokok' => round((float) $baris->sum('sisa_pokok'), 2),
            'sisa_bunga' => round((float) $baris->sum('sisa_bunga'), 2),
            'sudah_dibayar' => $baris->every(fn ($a) => $a->status !== 'belum_lunas'),
        ])->values();

        $lunas = Pinjaman::with('user')
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
            'pendapatan_administrasi' => round((float) Pinjaman::whereIn('status', ['aktif', 'lunas'])
                ->whereBetween('tanggal_pengajuan', [$awal->toDateString(), $akhir->toDateString()])
                ->sum('administrasi_pendapatan'), 2),
        ];
    }

    /**
     * Tutup pinjaman aktif dengan pelunasan: sisa angsuran ditandai `pelunasan`,
     * piutang potong gaji yang belum jatuh tempo dihapus (tidak akan dipotong lagi),
     * dan total pokok/bunga yang dibayar dicatat terpisah.
     */
    private function tutupDenganPelunasan(Pinjaman $pinjaman, RincianPelunasan $rincian, ?User $oleh, ?int $dikompensasiOleh = null): void
    {
        $sisaId = $pinjaman->angsuran()->where('status', 'belum_lunas')->pluck('id');

        Piutang::where('source_type', 'pinjaman')
            ->whereIn('source_id', $sisaId)
            ->where('status', 'belum_lunas')
            ->delete();

        PinjamanAngsuran::whereIn('id', $sisaId)->update([
            'status' => 'pelunasan',
            'dibayar_pada' => now(),
            'dibayar_oleh' => $oleh?->user_id,
        ]);

        $terbayar = $pinjaman->angsuran()->where('status', 'lunas')->get();

        $pinjaman->update([
            'status' => 'lunas',
            'pelunasan_pokok' => round((float) $terbayar->sum('pokok') + $rincian->sisaPokok, 2),
            'pelunasan_bunga' => round((float) $terbayar->sum('bunga') + $rincian->bungaDitagih, 2),
            'dilunasi_pada' => now(),
            'dikompensasi_oleh' => $dikompensasiOleh,
        ]);
    }

    /**
     * Potong gaji hanya boleh bila setiap angsuran, ditambah piutang lain di
     * periode jatuh temponya, masih di bawah batas max_potongan_persen x gaji.
     * Gaji/jabatan yang belum dikonfigurasi ditolak (fail closed, sama dengan
     * Piutang::wouldExceedLimit di kasir).
     *
     * @param  list<int>  $kecualiAngsuranId  angsuran pinjaman lama yang ditutup kompensasi
     */
    private function cekKecukupanGaji(User $anggota, JadwalAngsuran $jadwal, array $kecualiAngsuranId): ?string
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
                ->where(fn ($q) => $q->where('source_type', '!=', 'pinjaman')
                    ->orWhereNotIn('source_id', $kecualiAngsuranId))
                ->sum('amount');

            if ($piutangLain + $angsuran > $batas) {
                return "Potongan gaji periode {$periode} melebihi batas Rp".$this->rupiah($batas).'.';
            }
        }

        return null;
    }

    private function rupiah(float $nilai): string
    {
        return number_format($nilai, 0, ',', '.');
    }
}

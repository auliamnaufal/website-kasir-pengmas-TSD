<?php

namespace App\Services;

use App\Models\Piutang;
use App\Models\Simpanan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Simpanan wajib (nominal per jabatan, dari jabatan_configs) dan simpanan
 * sukarela. Periode mengikuti konvensi cutoff P1 (Piutang::periodFor).
 */
class SimpananService
{
    /**
     * Catat simpanan wajib periode $periode untuk setiap anggota yang
     * jabatannya terkonfigurasi. Aman dijalankan berulang: anggota yang sudah
     * punya simpanan wajib di periode itu dilewati.
     *
     * @return int jumlah setoran baru yang dibuat
     */
    public function generateWajib(string $periode, ?User $oleh = null): int
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
            throw new InvalidArgumentException('Periode harus berformat YYYY-MM.');
        }

        $anggota = User::whereNotNull('jabatan')->with('jabatanConfig')->get()
            ->filter(fn (User $user) => (float) ($user->jabatanConfig?->simpanan_wajib ?? 0) > 0);

        return DB::transaction(function () use ($anggota, $periode, $oleh) {
            $sudahAda = Simpanan::where('jenis', 'wajib')->where('periode', $periode)
                ->pluck('user_id')->all();

            $dibuat = 0;
            foreach ($anggota as $user) {
                if (in_array($user->user_id, $sudahAda)) {
                    continue;
                }
                Simpanan::create([
                    'user_id' => $user->user_id,
                    'jenis' => 'wajib',
                    'jumlah' => $user->jabatanConfig->simpanan_wajib,
                    'tanggal' => now()->toDateString(),
                    'periode' => $periode,
                    'keterangan' => "Simpanan wajib {$user->jabatan}",
                    'dibuat_oleh' => $oleh?->user_id,
                ]);
                $dibuat++;
            }

            return $dibuat;
        });
    }

    public function setorSukarela(
        User $anggota,
        float $jumlah,
        CarbonInterface $tanggal,
        ?string $keterangan = null,
        ?User $oleh = null,
        ?string $sumberType = null,
        ?int $sumberId = null,
    ): Simpanan {
        if ($jumlah <= 0) {
            throw new InvalidArgumentException('Jumlah simpanan harus lebih dari 0.');
        }

        return Simpanan::create([
            'user_id' => $anggota->user_id,
            'jenis' => 'sukarela',
            'jumlah' => round($jumlah, 2),
            'tanggal' => $tanggal->toDateString(),
            'periode' => Piutang::periodFor($tanggal),
            'sumber_type' => $sumberType,
            'sumber_id' => $sumberId,
            'keterangan' => $keterangan,
            'dibuat_oleh' => $oleh?->user_id,
        ]);
    }

    /** @return array{wajib: float, sukarela: float, total: float} */
    public function saldo(User $anggota): array
    {
        $perJenis = Simpanan::where('user_id', $anggota->user_id)
            ->selectRaw('jenis, SUM(jumlah) as total')
            ->groupBy('jenis')
            ->pluck('total', 'jenis');

        $wajib = round((float) ($perJenis['wajib'] ?? 0), 2);
        $sukarela = round((float) ($perJenis['sukarela'] ?? 0), 2);

        return ['wajib' => $wajib, 'sukarela' => $sukarela, 'total' => round($wajib + $sukarela, 2)];
    }
}

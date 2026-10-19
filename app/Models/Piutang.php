<?php

namespace App\Models;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class Piutang extends Model
{
    protected $table = 'piutang';

    protected $fillable = [
        'user_id',
        'source_type',
        'source_id',
        'amount',
        'status',
        'period',
        'settled_at',
        'settled_by',
    ];

    protected $casts = [
        'settled_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function settledBy()
    {
        return $this->belongsTo(User::class, 'settled_by', 'user_id');
    }

    /**
     * Cutoff-period convention (spec issue #3/#9): a period window runs from
     * the 16th of the PREVIOUS month through the 15th of THIS month, and is
     * labeled ("Y-m") by the month containing its CLOSING day (the 15th) —
     * not the month containing its opening day (the 16th). So:
     *
     *   - day <= 15 -> window closes on the 15th of THIS month -> period = this month
     *   - day >= 16 -> window closes on the 15th of NEXT month -> period = next month
     */
    public static function periodFor(DateTimeInterface $date): string
    {
        $carbon = Carbon::instance($date);

        return $carbon->day >= 16
            ? $carbon->copy()->addMonth()->format('Y-m')
            : $carbon->format('Y-m');
    }

    /**
     * Would adding $newAmount to $pegawai's belum_lunas piutang for $period push
     * them past their jabatan's max_potongan_persen of gaji? A pegawai whose
     * gaji or jabatan_configs row isn't set up fails closed (treated as
     * exceeding) rather than being exempted from the check.
     */
    public static function wouldExceedLimit(User $pegawai, string $period, float $newAmount): bool
    {
        $jabatanConfig = $pegawai->jabatanConfig;

        if ($pegawai->gaji === null || $jabatanConfig === null) {
            return true;
        }

        $existingPiutang = static::where('user_id', $pegawai->user_id)
            ->where('status', 'belum_lunas')
            ->where('period', $period)
            ->sum('amount');

        $limit = $pegawai->gaji * $jabatanConfig->max_potongan_persen / 100;

        return ($existingPiutang + $newAmount) > $limit;
    }
}

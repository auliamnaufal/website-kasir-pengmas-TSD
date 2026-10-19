<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';
    protected $primaryKey = 'user_id';

    protected $fillable = [
        'email',
        'password',
        'name',
        'role',
        'nik',
        'jabatan',
        'gaji',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user) {
            if (empty($user->employee_id)) {
                // ponytail: count-based sequence (per spec), not max-based — could repeat an
                // employee_id after a delete+create cycle. Switch to a dedicated sequence/max-based
                // counter if that ever matters in practice.
                $sequence = static::whereNotNull('employee_id')->count() + 1;
                $user->employee_id = 'EMP'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    // This user's jabatan_configs row, matched by jabatan name (jabatan_configs
    // has no foreign-id column to key off of).
    public function jabatanConfig()
    {
        return $this->hasOne(JabatanConfig::class, 'jabatan', 'jabatan');
    }

    // Hierarchy level of this user's jabatan, per jabatan_configs. Missing/unconfigured
    // jabatan is treated as the lowest rung (0), never as "unrestricted".
    public function jabatanLevel(): int
    {
        return (int) ($this->jabatanConfig?->level ?? 0);
    }

    public function getHomeRouteAttribute()
    {
        switch ($this->role) {
            case 'admin':
                return route('admin.dashboard');
            case 'karyawan':
                return route('kasir.index');
            default:
                return route('login');
        }
    }
}

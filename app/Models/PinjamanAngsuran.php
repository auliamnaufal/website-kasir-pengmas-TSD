<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PinjamanAngsuran extends Model
{
    protected $table = 'pinjaman_angsuran';

    protected $fillable = [
        'pinjaman_id',
        'bulan_ke',
        'jatuh_tempo',
        'pokok',
        'bunga',
        'sisa_pokok',
        'sisa_bunga',
        'status',
        'dibayar_pada',
        'dibayar_oleh',
        'kode_perkiraan',
    ];

    protected $casts = [
        'jatuh_tempo' => 'date',
        'dibayar_pada' => 'datetime',
    ];

    public function pinjaman()
    {
        return $this->belongsTo(Pinjaman::class, 'pinjaman_id');
    }

    public function total(): float
    {
        return round((float) $this->pokok + (float) $this->bunga, 2);
    }

    // Baris piutang potong gaji untuk angsuran ini (hanya ada bila metode_bayar = potong_gaji).
    public function piutang()
    {
        return $this->hasOne(Piutang::class, 'source_id')->where('source_type', 'pinjaman');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KreditAngsuran extends Model
{
    protected $table = 'kredit_angsuran';

    protected $fillable = [
        'kredit_barang_id',
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

    public function kredit()
    {
        return $this->belongsTo(KreditBarang::class, 'kredit_barang_id');
    }

    public function total(): float
    {
        return round((float) $this->pokok + (float) $this->bunga, 2);
    }

    // Baris piutang potong gaji untuk angsuran ini (hanya ada bila metode_bayar = potong_gaji).
    public function piutang()
    {
        return $this->hasOne(Piutang::class, 'source_id')->where('source_type', 'kredit');
    }
}

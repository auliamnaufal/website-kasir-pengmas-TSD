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
        'status',
        'dibayar_pada',
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
}

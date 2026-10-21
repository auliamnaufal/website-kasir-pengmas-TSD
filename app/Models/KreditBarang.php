<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KreditBarang extends Model
{
    protected $table = 'kredit_barang';

    protected $fillable = [
        'user_id',
        'nama_barang',
        'pokok',
        'tenor',
        'administrasi',
        'bunga_persen',
        'tanggal_pengajuan',
        'tanggal_mulai',
        'status',
        'alasan_penolakan',
        'kode_perkiraan',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'tanggal_mulai' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function angsuran()
    {
        return $this->hasMany(KreditAngsuran::class, 'kredit_barang_id');
    }
}

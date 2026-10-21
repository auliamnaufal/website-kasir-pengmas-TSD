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
        'metode_bayar',
        'administrasi',
        'bunga_persen',
        'metode_bunga',
        'bunga_sampai_bulan',
        'bulan_bunga_minimum',
        'tanggal_pengajuan',
        'tanggal_mulai',
        'status',
        'alasan_penolakan',
        'pelunasan_pokok',
        'pelunasan_bunga',
        'dilunasi_pada',
        'dibuat_oleh',
        'kode_perkiraan',
    ];

    protected $casts = [
        'tanggal_pengajuan' => 'date',
        'tanggal_mulai' => 'date',
        'dilunasi_pada' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function angsuran()
    {
        return $this->hasMany(KreditAngsuran::class, 'kredit_barang_id')->orderBy('bulan_ke');
    }

    public function nomor(): string
    {
        return 'KB-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function angsuranTerbayar(): int
    {
        return $this->angsuran->where('status', 'lunas')->count();
    }

    /** Sisa pokok saat ini = pokok dari angsuran yang belum dibayar. */
    public function sisaPokok(): float
    {
        return round((float) $this->angsuran->where('status', 'belum_lunas')->sum('pokok'), 2);
    }

    /** Sisa bunga terjadwal dari angsuran yang belum dibayar. */
    public function sisaBunga(): float
    {
        return round((float) $this->angsuran->where('status', 'belum_lunas')->sum('bunga'), 2);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pinjaman extends Model
{
    protected $table = 'pinjaman';

    protected $fillable = [
        'user_id',
        'pokok',
        'tenor',
        'bunga_persen',
        'metode_bunga',
        'bulan_bunga_minimum',
        'administrasi',
        'administrasi_pendapatan',
        'administrasi_sukarela',
        'kompensasi',
        'dana_diterima',
        'metode_bayar',
        'tanggal_pengajuan',
        'tanggal_mulai',
        'status',
        'alasan_penolakan',
        'pelunasan_pokok',
        'pelunasan_bunga',
        'dilunasi_pada',
        'dikompensasi_oleh',
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
        return $this->hasMany(PinjamanAngsuran::class, 'pinjaman_id')->orderBy('bulan_ke');
    }

    public function dikompensasiOleh()
    {
        return $this->belongsTo(Pinjaman::class, 'dikompensasi_oleh');
    }

    public function nomor(): string
    {
        return 'SP-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
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

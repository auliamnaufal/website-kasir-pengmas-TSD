<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JabatanConfig extends Model
{
    protected $fillable = ['jabatan', 'level', 'simpanan_wajib', 'max_potongan_persen'];
}

<?php

use App\Models\Piutang;
use App\Services\SimpananService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Simpanan wajib otomatis per jabatan (issue #1). Jalan tiap tanggal 16, awal
// window periode cutoff baru; aman diulang karena anggota yang sudah tercatat dilewati.
Artisan::command('simpanan:generate-wajib {periode? : YYYY-MM, default periode berjalan}', function (SimpananService $service) {
    $periode = $this->argument('periode') ?? Piutang::periodFor(now());
    $dibuat = $service->generateWajib($periode);
    $this->info("{$dibuat} simpanan wajib dicatat untuk periode {$periode}.");
})->purpose('Catat simpanan wajib semua anggota untuk satu periode')->monthlyOn(16, '01:00');

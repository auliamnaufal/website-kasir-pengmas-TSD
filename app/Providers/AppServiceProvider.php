<?php

namespace App\Providers;

use App\Models\User;
use App\Services\Laporan\PosUnitLaporan;
use App\Services\LaporanService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Unit usaha yang ikut laporan lintas unit. Unit baru (simpan pinjam, kredit)
        // cukup menambah kelasnya ke daftar tag ini.
        $this->app->tag([PosUnitLaporan::class], 'laporan.unit');
        $this->app->bind(LaporanService::class, fn ($app) => new LaporanService($app->tagged('laporan.unit')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Reusable tiered-authorization gate (P1-3 / issue #8): allow if the actor is admin,
        // or if the actor's jabatan level outranks the target's. Reused as-is by P2/P3 approvals.
        Gate::define('updateSalary', function (User $actor, User $target) {
            return $actor->role === 'admin' || $actor->jabatanLevel() > $target->jabatanLevel();
        });
    }
}

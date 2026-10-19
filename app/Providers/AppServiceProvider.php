<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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

<?php

namespace App\Providers;

use App\Enums\AdminRole;
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
        foreach (AdminRole::PERMISSIONS as $permission) {
            Gate::define(
                $permission,
                fn (User $user) => $user->hasPermission($permission)
            );
        }
    }
}

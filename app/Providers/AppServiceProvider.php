<?php

namespace App\Providers;

use App\Enums\AdminRole;
use App\Models\User;
use App\Services\Sms\LogSmsProvider;
use App\Services\Sms\SmsIrProvider;
use App\Services\Sms\SmsProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SmsProvider::class, function () {
            return match (config('services.sms.driver')) {
                'smsir' => new SmsIrProvider(
                    config('services.sms.smsir.api_key'),
                    config('services.sms.smsir.base_url'),
                    (int) config('services.sms.smsir.timeout'),
                ),
                default => new LogSmsProvider(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        foreach (AdminRole::PERMISSIONS as $permission) {
            Gate::define(
                $permission,
                fn (User $user) => $user->hasPermission($permission)
            );
        }
    }
}

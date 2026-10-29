<?php

namespace App\Providers;

use App\Enums\AdminRole;
use App\Models\User;
use App\Services\Sms\LogSmsProvider;
use App\Services\Sms\SmsIrProvider;
use App\Services\Sms\SmsProvider;
use Illuminate\Pagination\Paginator;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
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
                    config('services.sms.smsir.line_number'),
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

        // /up برای پایش: خطای اتصال پایگاه داده پاسخ 500 می‌دهد
        Event::listen(DiagnosingHealth::class, fn () => DB::select('select 1'));

        // روی هاست ممکن است HTTPS در پروکسی تمام شود؛ نشانی‌های ساخته‌شده
        // (مثلاً بازگشت درگاه) با https مطابق APP_URL ساخته شوند
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        foreach (AdminRole::PERMISSIONS as $permission) {
            Gate::define(
                $permission,
                fn (User $user) => $user->hasPermission($permission)
            );
        }
    }
}

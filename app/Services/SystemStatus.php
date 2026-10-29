<?php

namespace App\Services;

use App\Models\SmsTemplate;
use App\Services\Sms\SmsDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * وضعیت پیکربندی سرور برای مالک بدون دسترسی SSH (مراحل ۸۷ و ۸۸).
 * مقدار هیچ کلید یا رمزی نمایش داده نمی‌شود؛ فقط «تنظیم شده / نشده».
 *
 * هر بررسی: [گروه, عنوان, وضعیت ok|warn|error, توضیح, راه‌حل]
 */
class SystemStatus
{
    public const REQUIRED_EXTENSIONS = ['pdo_mysql', 'mbstring', 'openssl', 'curl', 'fileinfo'];

    /**
     * @return list<array{group: string, label: string, status: string, detail: string, fix: ?string}>
     */
    public function checks(): array
    {
        $production = app()->isProduction();
        $checks = [];
        $add = function (string $group, string $label, string $status, string $detail, ?string $fix = null) use (&$checks) {
            $checks[] = compact('group', 'label', 'status', 'detail', 'fix');
        };

        // برنامه
        $add('برنامه', 'محیط اجرا', $production ? 'ok' : 'warn', 'APP_ENV=' . app()->environment(), $production ? null : 'روی سرور APP_ENV=production باشد.');
        $add('برنامه', 'حالت اشکال‌زدایی', config('app.debug') ? ($production ? 'error' : 'warn') : 'ok', config('app.debug') ? 'روشن' : 'خاموش', config('app.debug') ? 'APP_DEBUG=false (نمایش جزئیات خطا اطلاعات حساس را افشا می‌کند).' : null);
        $add('برنامه', 'کلید رمزنگاری', filled(config('app.key')) ? 'ok' : 'error', filled(config('app.key')) ? 'تنظیم شده' : 'تنظیم نشده', filled(config('app.key')) ? null : 'APP_KEY را تولید و در .env قرار دهید.');
        $https = str_starts_with((string) config('app.url'), 'https://');
        $add('برنامه', 'نشانی سایت', $https ? 'ok' : ($production ? 'error' : 'warn'), (string) config('app.url'), $https ? null : 'APP_URL با https و دامنه واقعی (برای بازگشت درگاه و لینک‌ها).');
        $secureCookie = (bool) config('session.secure');
        $add('برنامه', 'کوکی امن نشست', $secureCookie ? 'ok' : ($production ? 'error' : 'warn'), $secureCookie ? 'فقط HTTPS' : 'روی HTTP هم ارسال می‌شود', $secureCookie ? null : 'SESSION_SECURE_COOKIE=true (پس از فعال بودن HTTPS)');
        $add('برنامه', 'منطقه زمانی نمایش', 'ok', (string) config('app.display_timezone'));
        $add('برنامه', 'نسخه PHP', version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'error', PHP_VERSION);
        $missing = array_values(array_filter(self::REQUIRED_EXTENSIONS, fn ($ext) => ! extension_loaded($ext)));
        $add('برنامه', 'افزونه‌های PHP', $missing ? 'error' : 'ok', $missing ? 'فعال نیست: ' . implode(', ', $missing) : implode(', ', self::REQUIRED_EXTENSIONS), $missing ? 'cPanel ← Select PHP Version ← Extensions' : null);
        $add('برنامه', 'کش پیکربندی', app()->configurationIsCached() ? 'ok' : 'warn', app()->configurationIsCached() ? 'فعال' : 'غیرفعال', app()->configurationIsCached() ? null : 'برای سرعت بیشتر: php artisan config:cache (پس از هر تغییر .env دوباره).');

        // پایگاه داده
        try {
            $pdo = DB::connection()->getPdo();
            $driver = DB::connection()->getDriverName();
            $add('پایگاه داده', 'اتصال', in_array($driver, ['mysql', 'mariadb'], true) ? 'ok' : 'warn', $driver . ' ' . $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION), in_array($driver, ['mysql', 'mariadb'], true) ? null : 'روی سرور DB_CONNECTION=mariadb یا mysql باشد.');

            $migrator = app('migrator');
            $files = array_keys($migrator->getMigrationFiles(database_path('migrations')));
            $pending = $migrator->repositoryExists() ? array_values(array_diff($files, $migrator->getRepository()->getRan())) : $files;
            $add('پایگاه داده', 'migrationها', $pending ? 'error' : 'ok', $pending ? count($pending) . ' اجرا نشده (اولین: ' . $pending[0] . ')' : 'همه اجرا شده (' . count($files) . ')', $pending ? 'migrationهای اجرانشده را اجرا کنید؛ تا آن زمان بخش‌هایی از پنل کار نمی‌کند.' : null);
        } catch (Throwable $e) {
            $add('پایگاه داده', 'اتصال', 'error', 'برقرار نشد', 'DB_HOST، DB_DATABASE، DB_USERNAME و DB_PASSWORD را در .env بررسی کنید.');
        }

        // پوشه‌ها
        foreach (['storage' => storage_path(), 'public/uploads' => public_path('uploads'), 'bootstrap/cache' => base_path('bootstrap/cache')] as $name => $path) {
            $writable = is_dir($path) && is_writable($path);
            $add('پوشه‌ها', $name, $writable ? 'ok' : 'error', $writable ? 'قابل نوشتن' : 'قابل نوشتن نیست', $writable ? null : 'در File Manager مجوز پوشه را 755 (یا 775) کنید.');
        }

        // پیامک
        $driver = config('services.sms.driver');
        $add('پیامک', 'ارسال‌کننده', $driver === 'smsir' ? 'ok' : ($production ? 'error' : 'warn'), $driver === 'smsir' ? 'SMS.ir' : 'آزمایشی (لاگ)', $driver === 'smsir' ? null : 'SMS_DRIVER=smsir');
        $add('پیامک', 'کلید API', filled(config('services.sms.smsir.api_key')) ? 'ok' : 'error', filled(config('services.sms.smsir.api_key')) ? 'تنظیم شده' : 'تنظیم نشده', filled(config('services.sms.smsir.api_key')) ? null : 'SMSIR_API_KEY در .env');
        $add('پیامک', 'خط اختصاصی', filled(config('services.sms.smsir.line_number')) ? 'ok' : 'warn', filled(config('services.sms.smsir.line_number')) ? 'تنظیم شده' : 'تنظیم نشده', filled(config('services.sms.smsir.line_number')) ? null : 'برای ارسال گروهی و دستی: SMSIR_LINE_NUMBER');

        try {
            $templates = SmsTemplate::all();
            $otp = $templates->firstWhere('key', 'otp');
            $add('پیامک', 'قالب کد ورود', $otp?->isConfigured() ? 'ok' : 'error', $otp?->isConfigured() ? 'شناسه ' . $otp->provider_template_id : 'تنظیم نشده', $otp?->isConfigured() ? null : 'پنل ← قالب‌های پیامک ← کد ورود مخاطب');
            $notifications = $templates->where('category', 'transactional');
            $add('پیامک', 'قالب‌های اطلاع‌رسانی', $notifications->where('enabled', true)->isNotEmpty() ? 'ok' : 'warn', $notifications->where('enabled', true)->count() . ' از ' . $notifications->count() . ' فعال', null);
        } catch (Throwable) {
            // جدول قبل از اجرای migration وجود ندارد؛ در بخش پایگاه داده گزارش شده است
        }

        $lastRun = Cache::get(SmsDispatcher::LAST_RUN_CACHE_KEY);
        $recent = $lastRun && Carbon::parse($lastRun)->gt(now()->subMinutes(5));
        $add('پیامک', 'Cron صف پیامک', $recent ? 'ok' : 'warn', $lastRun ? 'آخرین اجرا: ' . \App\Support\Format::datetime(Carbon::parse($lastRun)) : 'هرگز اجرا نشده', $recent ? null : 'Cron Job هر دقیقه: php /home/USER/idea-lab/artisan schedule:run');

        // زرین‌پال
        $merchant = filled(config('services.zarinpal.merchant_id'));
        $sandbox = filter_var(config('services.zarinpal.sandbox'), FILTER_VALIDATE_BOOL);
        $add('درگاه پرداخت', 'شناسه پذیرنده زرین‌پال', $merchant ? 'ok' : 'error', $merchant ? 'تنظیم شده' : 'تنظیم نشده', $merchant ? null : 'ZARINPAL_MERCHANT_ID در .env');
        $add('درگاه پرداخت', 'حالت آزمایشی (sandbox)', $sandbox ? ($production ? 'error' : 'warn') : 'ok', $sandbox ? 'روشن — پرداخت واقعی انجام نمی‌شود' : 'خاموش', $sandbox ? 'برای پرداخت واقعی: ZARINPAL_SANDBOX=false' : null);

        return $checks;
    }
}

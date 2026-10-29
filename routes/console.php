<?php

use App\Services\Sms\SmsDispatcher;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * ارسال پیامک‌های صف. روی cPanel یک Cron Job هر دقیقه:
 *   php /home/USER/idea-lab/artisan schedule:run
 * بدون cron هم پیامک‌های تراکنشی پس از پاسخ به کاربر ارسال می‌شوند و
 * ارسال گروهی از صفحه آن در پنل دسته‌به‌دسته قابل ادامه است.
 */
Artisan::command('sms:dispatch {--limit= : حداکثر پیام در این اجرا}', function (SmsDispatcher $dispatcher) {
    $stats = $dispatcher->run($this->option('limit') ? (int) $this->option('limit') : null);

    $this->info(sprintf('sent: %d, failed: %d, retry: %d, skipped: %d', $stats['sent'], $stats['failed'], $stats['retry'], $stats['skipped']));
})->purpose('Send queued SMS messages');

Schedule::command('sms:dispatch')->everyMinute()->withoutOverlapping(10);

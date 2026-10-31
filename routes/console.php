<?php

use App\Models\SystemUpdate;
use App\Services\Backup\DatabaseBackup;
use App\Services\Sms\SmsDispatcher;
use App\Services\Updates\ReleaseBuilder;
use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdateInstaller;
use App\Services\Updates\UpdatePackage;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

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

/*
 * پشتیبان روزانه پایگاه داده در storage/app/backups (نگه‌داری BACKUP_KEEP فایل آخر).
 * بازیابی: phpMyAdmin ← پایگاه داده ← Import فایل .sql.gz
 */
Artisan::command('backup:database {--keep= : تعداد پشتیبان نگه‌داشته‌شده}', function (DatabaseBackup $backup) {
    $path = $backup->create();
    $removed = $backup->prune((int) ($this->option('keep') ?: config('backup.keep')));

    $this->info('Backup created: ' . basename($path) . ' (' . number_format(filesize($path)) . ' bytes); removed ' . $removed . ' old file(s).');
})->purpose('Create a gzip SQL backup of the database');

Schedule::command('backup:database')->dailyAt('23:40')->withoutOverlapping(60);

/*
 * ساخت بسته نصب/به‌روزرسانی (GitHub Actions ← release.yml). --lite بدون vendor
 * است و فقط وقتی نصب می‌شود که composer.lock سرور با بسته یکی باشد.
 */
Artisan::command('release:build {version} {--output=} {--lite} {--commit=}', function (ReleaseBuilder $builder) {
    $output = $this->option('output') ?: base_path('dist/idea-lab-' . $this->argument('version') . ($this->option('lite') ? '-lite' : '') . '.zip');
    $manifest = $builder->build(base_path(), $this->argument('version'), $output, ! $this->option('lite'), $this->option('commit') ?: null);

    $this->info(sprintf('%s: %d files, sha256 %s', $output, count($manifest['files']), hash_file('sha256', $output)));
})->purpose('Build an installable update package with a checksum manifest');

/*
 * به‌روزرسانی بدون پنل (وقتی برنامه بالا نمی‌آید)، با یک Cron یک‌باره در cPanel:
 *   cd /home/USER/idea-lab && php artisan system:update storage/app/updates/idea-lab-1.1.0.zip && php artisan system:finish-update
 * تکمیل در فرایند جدا اجرا می‌شود تا migrationها با کد جدید بارگذاری شوند.
 */
Artisan::command('system:update {package} {--reinstall}', function (UpdateInstaller $installer) {
    $path = $this->argument('package');
    $path = str_starts_with($path, '/') ? $path : base_path($path);

    try {
        $update = $installer->apply(UpdatePackage::open($path), null, 'cli', (bool) $this->option('reinstall'));
    } catch (UpdateException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->info("Files of {$update->to_version} applied (update #{$update->id}). Now run: php artisan system:finish-update");
})->purpose('Apply an update package (step 1 of 2)');

/*
 * مرحله دوم به‌روزرسانی؛ پس از Extract دستی بسته در File Manager هم همین را
 * اجرا کنید (migrate، ساخت کش، ثبت نسخه و خروج از حالت تعمیر).
 */
Artisan::command('system:finish-update', function (UpdateInstaller $installer) {
    // نصب نخست این سامانه: جدول تاریخچه هنوز ساخته نشده است
    $pending = Schema::hasTable('system_updates')
        ? SystemUpdate::where('status', SystemUpdate::FILES_APPLIED)->latest('id')->first()
        : null;

    try {
        $update = $pending ? $installer->finish($pending) : $installer->finishManual(null);
    } catch (UpdateException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->info("Version {$update->to_version} is installed (update #{$update->id}).");
})->purpose('Run migrations and rebuild caches after an update (step 2 of 2)');

Artisan::command('system:rollback {update? : شماره به‌روزرسانی؛ پیش‌فرض آخرین}', function (UpdateInstaller $installer) {
    $update = $this->argument('update')
        ? SystemUpdate::find($this->argument('update'))
        : SystemUpdate::whereIn('status', [SystemUpdate::COMPLETED, SystemUpdate::APPLYING, SystemUpdate::FILES_APPLIED])->whereNotNull('package')->latest('id')->first();

    if (! $update) {
        $this->error('No update to roll back.');

        return 1;
    }

    try {
        $installer->rollback($update);
    } catch (UpdateException $e) {
        $this->error($e->getMessage());

        return 1;
    }

    $this->info("Update #{$update->id} rolled back to {$update->from_version}.");
})->purpose('Restore the code files replaced by an update');

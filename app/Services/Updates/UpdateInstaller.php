<?php

namespace App\Services\Updates;

use App\Models\SystemUpdate;
use App\Models\User;
use App\Services\Backup\DatabaseBackup;
use App\Services\CacheRebuilder;
use App\Support\AppVersion;
use App\Support\Format;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;
use ZipArchive;

/**
 * نصب بسته به‌روزرسانی روی هاست بدون SSH (مراحل ۱۰۵ و ۱۰۶).
 *
 * دو مرحله در دو درخواست/فرایند جدا اجرا می‌شود تا migrationها با کد جدید
 * بارگذاری شوند، نه با کلاس‌های قدیمی که در حافظه همین فرایند مانده‌اند:
 *   apply():  پشتیبان پایگاه داده ← نسخه پشتیبان فایل‌ها ← حالت تعمیر ← جایگزینی فایل‌ها
 *   finish(): migrate ← ساخت کش ← خروج از حالت تعمیر
 * هر شکستی فایل‌های قبلی را برمی‌گرداند. چون migrationها فقط افزایشی‌اند
 * (ROADMAP_V2.md)، کد قبلی با پایگاه داده جدید هم کار می‌کند.
 */
class UpdateInstaller
{
    public const INSTALLED_MANIFEST = 'installed-manifest.json';

    private const KEEP_ROLLBACKS = 3;

    public function __construct(
        private readonly DatabaseBackup $backup,
        private readonly CacheRebuilder $cache,
    ) {
    }

    public function target(): string
    {
        return rtrim(config('updates.target') ?: base_path(), '/');
    }

    public function directory(): string
    {
        // پس از Extract دستی، کش پیکربندی نسخه قبل این کلید را ندارد
        $directory = config('updates.directory') ?: storage_path('app/updates');
        File::ensureDirectoryExists($directory, 0750);

        return $directory;
    }

    public function currentVersion(): string
    {
        return AppVersion::read($this->target());
    }

    /**
     * فایل‌هایی که تغییر می‌کنند، بدون نوشتن چیزی.
     *
     * @return array{changed: list<string>, added: list<string>, removed: list<string>, modified_kept: list<string>, protected: list<string>, unchanged: int, new_migrations: list<string>}
     */
    public function plan(UpdatePackage $package): array
    {
        $target = $this->target();
        $plan = ['changed' => [], 'added' => [], 'removed' => [], 'modified_kept' => [], 'protected' => [], 'unchanged' => 0];

        foreach ($package->files() as $path => $hash) {
            $file = $target . '/' . $path;

            if (PackagePaths::isProtected($path)) {
                $plan['protected'][] = $path;
            } elseif (! is_file($file)) {
                $plan['added'][] = $path;
            } elseif ($this->isSame($package, $path, $hash, $file)) {
                $plan['unchanged']++;
            } else {
                $plan['changed'][] = $path;
            }
        }

        // فقط فایل‌هایی حذف می‌شوند که نسخه قبلی نصب کرده و دست‌نخورده مانده‌اند
        foreach ($this->installedManifest()['files'] ?? [] as $path => $hash) {
            $path = (string) $path;

            if (isset($package->files()[$path]) || ! PackagePaths::isSafe($path) || PackagePaths::isProtected($path) || ! is_file($target . '/' . $path)) {
                continue;
            }

            // بسته سبک vendor ندارد؛ نبودن آن در manifest به معنی حذف کتابخانه‌ها نیست
            if (! $package->vendorIncluded() && str_starts_with($path, 'vendor/')) {
                continue;
            }

            hash_equals((string) $hash, (string) hash_file('sha256', $target . '/' . $path))
                ? $plan['removed'][] = $path
                : $plan['modified_kept'][] = $path;
        }

        $plan['new_migrations'] = array_values(array_diff($package->migrations(), $this->ranMigrations()));

        return $plan;
    }

    /**
     * @return list<array{label: string, ok: bool, blocking: bool, detail: string}>
     */
    public function checks(UpdatePackage $package, array $plan, bool $reinstall = false): array
    {
        $checks = [];
        $add = function (string $label, bool $ok, string $detail, bool $blocking = true) use (&$checks) {
            $checks[] = ['label' => $label, 'ok' => $ok, 'blocking' => $blocking && ! $ok, 'detail' => $detail];
        };

        $current = $this->currentVersion();
        $compare = version_compare($package->version(), $current);
        [$from, $to] = [Format::ltr($current), Format::ltr($package->version())];
        $add('نسخه', $compare > 0 || ($compare === 0 && $reinstall), match (true) {
            $compare > 0 => Format::ltr("{$current} → {$package->version()}"),
            $compare === 0 => $reinstall ? "نصب دوباره {$from}" : "نسخه {$from} هم‌اکنون نصب است؛ برای نصب دوباره گزینه آن را انتخاب کنید.",
            default => "بسته ({$to}) قدیمی‌تر از نسخه نصب‌شده ({$from}) است.",
        });

        if (! $package->vendorIncluded()) {
            $lock = $this->target() . '/composer.lock';
            $same = is_file($lock) && hash_equals($package->composerLockHash(), (string) hash_file('sha256', $lock));
            $add('کتابخانه‌ها (vendor)', $same, $same ? 'بسته سبک؛ کتابخانه‌ها تغییری نکرده‌اند.' : 'این بسته سبک است و vendor ندارد، ولی کتابخانه‌های این نسخه تغییر کرده‌اند؛ بسته کامل را بارگذاری کنید.');
        }

        $pending = SystemUpdate::whereIn('status', [SystemUpdate::APPLYING, SystemUpdate::FILES_APPLIED])->first();
        $add('به‌روزرسانی نیمه‌کاره', $pending === null, $pending ? 'به‌روزرسانی #' . $pending->id . ' هنوز تکمیل نشده است؛ ابتدا آن را تکمیل یا برگردانید.' : 'ندارد');

        $notWritable = $this->notWritable($plan);
        $add('مجوز نوشتن', $notWritable === [], $notWritable === [] ? 'همه مسیرها قابل نوشتن‌اند.' : 'قابل نوشتن نیست: ' . implode('، ', array_slice($notWritable, 0, 5)) . ' (مجوز 755 در File Manager)');

        $free = @disk_free_space($this->target());
        $need = $package->bytes() * 2;
        $add('فضای دیسک', $free === false || $free > $need, $free === false ? 'قابل سنجش نیست' : 'آزاد: ' . $this->megabytes((int) $free) . '، لازم: حدود ' . $this->megabytes($need), $free !== false);

        $add('پشتیبان پایگاه داده', $this->canBackup(), $this->canBackup() ? 'پیش از جایگزینی فایل‌ها خودکار گرفته می‌شود.' : 'فقط برای MySQL/MariaDB؛ پیش از ادامه دستی پشتیبان بگیرید.', false);

        return $checks;
    }

    public function apply(UpdatePackage $package, ?User $user, string $method, bool $reinstall = false): SystemUpdate
    {
        @set_time_limit(0);
        ignore_user_abort(true);

        $plan = $this->plan($package);
        $blocking = collect($this->checks($package, $plan, $reinstall))->where('blocking', true)->pluck('detail');

        if ($blocking->isNotEmpty()) {
            throw new UpdateException($blocking->implode(' '));
        }

        $lock = Cache::lock('system:update', 1800);
        if (! $lock->get()) {
            throw new UpdateException('به‌روزرسانی دیگری در حال اجراست.');
        }

        $update = null;
        $touched = false;
        $wentDown = false;

        try {
            $update = SystemUpdate::create([
                'from_version' => $this->currentVersion(),
                'to_version' => $package->version(),
                'package' => basename($package->path),
                'method' => $method,
                'status' => SystemUpdate::APPLYING,
                'user_id' => $user?->id,
                'summary' => $this->summary($package, $plan),
                'started_at' => now(),
            ]);

            if ($this->canBackup()) {
                $update->update(['backup_file' => basename($this->backup->create())]);
            }

            $this->writeRollbackArchive($update, $plan);
            File::put($this->path("manifest-{$update->id}.json"), $package->manifestJson());

            if (is_file($this->path(self::INSTALLED_MANIFEST))) {
                File::copy($this->path(self::INSTALLED_MANIFEST), $this->path("manifest-before-{$update->id}.json"));
            }

            if (! app()->isDownForMaintenance()) {
                Artisan::call('down', ['--retry' => 60]);
                $wentDown = true;
            }

            $touched = true;

            foreach ([...$plan['changed'], ...$plan['added']] as $path) {
                $this->writeFile($path, $package->content($path), merge: true);
            }

            foreach ($plan['removed'] as $path) {
                if (! @unlink($this->target() . '/' . $path)) {
                    throw new UpdateException('حذف فایل قدیمی ' . $path . ' ناموفق بود.');
                }
            }

            $this->resetOpcache();
            $this->clearBootCaches();

            $update->update([
                'status' => SystemUpdate::FILES_APPLIED,
                'summary' => [...$update->summary, 'maintenance' => $wentDown],
            ]);

            return $update;
        } catch (Throwable $e) {
            report($e);

            if ($touched) {
                $this->restoreFiles($update);
            }

            if ($wentDown) {
                Artisan::call('up');
            }

            $update?->update(['status' => SystemUpdate::FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);

            throw $e instanceof UpdateException
                ? $e
                : new UpdateException('جایگزینی فایل‌ها ناموفق بود' . ($touched ? ' و فایل‌های قبلی برگردانده شد' : '') . ': ' . $e->getMessage());
        } finally {
            $lock->release();
        }
    }

    public function finish(SystemUpdate $update): SystemUpdate
    {
        if ($update->status !== SystemUpdate::FILES_APPLIED) {
            throw new UpdateException('این به‌روزرسانی در انتظار تکمیل نیست.');
        }

        @set_time_limit(0);
        ignore_user_abort(true);

        try {
            Artisan::call('migrate', ['--force' => true, '--path' => $this->target() . '/database/migrations', '--realpath' => true]);
            $output = trim(Artisan::output());

            $this->cache->rebuild(app()->isProduction());

            if (is_file($this->path("manifest-{$update->id}.json"))) {
                File::copy($this->path("manifest-{$update->id}.json"), $this->path(self::INSTALLED_MANIFEST));
            }

            $this->leaveMaintenance($update);

            $update->update([
                'status' => SystemUpdate::COMPLETED,
                'summary' => [...$update->summary, 'migrate_output' => mb_substr($output, 0, 4000)],
                'finished_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            $this->restoreFiles($update);
            $this->clearCaches();
            $this->leaveMaintenance($update);

            $update->update(['status' => SystemUpdate::FAILED, 'error' => mb_substr($e->getMessage(), 0, 2000), 'finished_at' => now()]);

            throw new UpdateException('اجرای migration یا ساخت کش ناموفق بود و فایل‌های نسخه قبل برگردانده شد. اگر بخشی از migration اجرا شده باشد، نسخه قبل همچنان کار می‌کند؛ پشتیبان پایگاه داده: ' . ($update->backup_file ?: 'ندارد'));
        }

        $this->prune();

        return $update;
    }

    /**
     * پس از Extract دستی بسته در File Manager: migrate، ساخت کش و ثبت نسخه.
     */
    public function finishManual(?User $user, string $method = 'manual'): SystemUpdate
    {
        $this->clearBootCaches();

        // migrate اول: در نصب نخست این سامانه جدول system_updates هنوز وجود ندارد
        Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());

        $from = SystemUpdate::where('status', SystemUpdate::COMPLETED)->latest('id')->value('to_version') ?? AppVersion::UNVERSIONED;

        $this->cache->rebuild(app()->isProduction());

        // manifest بسته در ریشه پروژه Extract شده است
        $manifest = json_decode((string) @file_get_contents($this->target() . '/' . ReleaseBuilder::MANIFEST), true);
        if (is_array($manifest) && ($manifest['name'] ?? null) === 'idea-lab' && is_array($manifest['files'] ?? null)) {
            File::copy($this->target() . '/' . ReleaseBuilder::MANIFEST, $this->path(self::INSTALLED_MANIFEST));
        }

        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }

        return SystemUpdate::create([
            'from_version' => $from,
            'to_version' => $this->currentVersion(),
            'method' => $method,
            'status' => SystemUpdate::COMPLETED,
            'user_id' => $user?->id,
            'summary' => ['migrate_output' => mb_substr($output, 0, 4000)],
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    public function canRollback(SystemUpdate $update): bool
    {
        if ($update->isPending()) {
            return true;
        }

        return $update->status === SystemUpdate::COMPLETED
            && is_file($this->path("rollback-{$update->id}.json"))
            && $update->id === (int) SystemUpdate::where('status', SystemUpdate::COMPLETED)->whereNotNull('package')->max('id')
            && ! SystemUpdate::where('id', '>', $update->id)->whereIn('status', [SystemUpdate::COMPLETED, SystemUpdate::APPLYING, SystemUpdate::FILES_APPLIED])->exists();
    }

    /**
     * بازگرداندن کد به نسخه پیش از این به‌روزرسانی (پایگاه داده دست نمی‌خورد).
     */
    public function rollback(SystemUpdate $update): SystemUpdate
    {
        if (! $this->canRollback($update)) {
            throw new UpdateException('بازگرداندن این به‌روزرسانی ممکن نیست (فقط آخرین به‌روزرسانی انجام‌شده یا نیمه‌کاره).');
        }

        $lock = Cache::lock('system:update', 1800);
        if (! $lock->get()) {
            throw new UpdateException('به‌روزرسانی دیگری در حال اجراست.');
        }

        try {
            $this->restoreFiles($update);

            $before = $this->path("manifest-before-{$update->id}.json");
            is_file($before)
                ? File::copy($before, $this->path(self::INSTALLED_MANIFEST))
                : File::delete($this->path(self::INSTALLED_MANIFEST));

            $this->clearCaches();
            $this->leaveMaintenance($update);

            $update->update(['status' => SystemUpdate::ROLLED_BACK, 'finished_at' => now()]);
        } finally {
            $lock->release();
        }

        return $update;
    }

    /**
     * @return list<array{name: string, size: int, modified_at: \Illuminate\Support\Carbon}>
     */
    public function packages(): array
    {
        return collect(glob($this->directory() . '/*.zip') ?: [])
            ->reject(fn ($file) => str_starts_with(basename($file), 'rollback-'))
            ->map(fn ($file) => ['name' => basename($file), 'size' => filesize($file), 'modified_at' => \Illuminate\Support\Carbon::createFromTimestamp(filemtime($file))])
            ->sortByDesc('modified_at')
            ->values()
            ->all();
    }

    public function packagePath(string $name): ?string
    {
        $path = $this->directory() . '/' . basename($name);

        return preg_match('/^[0-9A-Za-z][0-9A-Za-z._-]*\.zip$/', $name) && ! str_starts_with($name, 'rollback-') && is_file($path) ? $path : null;
    }

    private function isSame(UpdatePackage $package, string $path, string $hash, string $file): bool
    {
        if (basename($path) === '.htaccess') {
            $current = (string) file_get_contents($file);

            return Htaccess::merge($package->content($path), $current) === $current;
        }

        return hash_equals($hash, (string) hash_file('sha256', $file));
    }

    private function writeFile(string $path, string $content, bool $merge): void
    {
        $file = $this->target() . '/' . $path;
        $directory = dirname($file);

        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new UpdateException('ساخت پوشه ' . dirname($path) . ' ناموفق بود.');
        }

        if ($merge && basename($path) === '.htaccess') {
            $content = Htaccess::merge($content, is_file($file) ? (string) file_get_contents($file) : null);
        }

        // نوشتن در فایل موقت و جابه‌جایی، تا هیچ درخواستی فایل نیمه‌نوشته نبیند
        $temp = $file . '.idealab-new';
        if (@file_put_contents($temp, $content) === false) {
            throw new UpdateException('نوشتن ' . $path . ' ناموفق بود (مجوز پوشه را بررسی کنید).');
        }

        @chmod($temp, 0644);

        if (! @rename($temp, $file)) {
            @unlink($temp);

            throw new UpdateException('جایگزینی ' . $path . ' ناموفق بود.');
        }

        if (str_ends_with($path, '.php') && function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }

    private function writeRollbackArchive(SystemUpdate $update, array $plan): void
    {
        $zip = new ZipArchive;
        if ($zip->open($this->path("rollback-{$update->id}.zip"), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new UpdateException('ساخت نسخه پشتیبان فایل‌ها ناموفق بود.');
        }

        foreach ([...$plan['changed'], ...$plan['removed']] as $path) {
            $zip->addFile($this->target() . '/' . $path, $path);
        }

        if (! $zip->close()) {
            throw new UpdateException('ساخت نسخه پشتیبان فایل‌ها ناموفق بود (فضای دیسک؟).');
        }

        File::put($this->path("rollback-{$update->id}.json"), json_encode([
            'from_version' => $update->from_version,
            'restore' => [...$plan['changed'], ...$plan['removed']],
            'added' => $plan['added'],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function restoreFiles(?SystemUpdate $update): void
    {
        $meta = $update ? json_decode((string) @file_get_contents($this->path("rollback-{$update->id}.json")), true) : null;

        if (! is_array($meta)) {
            return;
        }

        $zip = new ZipArchive;
        $opened = $zip->open($this->path("rollback-{$update->id}.zip"), ZipArchive::RDONLY) === true;

        foreach ($opened ? $meta['restore'] : [] as $path) {
            $content = $zip->getFromName($path);

            if ($content !== false) {
                $this->writeFile($path, $content, merge: false);
            }
        }

        foreach ($meta['added'] as $path) {
            if (PackagePaths::isSafe($path) && ! PackagePaths::isProtected($path)) {
                @unlink($this->target() . '/' . $path);
            }
        }

        $this->resetOpcache();
    }

    private function leaveMaintenance(SystemUpdate $update): void
    {
        if (($update->summary['maintenance'] ?? false) && app()->isDownForMaintenance()) {
            Artisan::call('up');
        }
    }

    private function clearCaches(): void
    {
        try {
            $this->cache->rebuild(false);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * کش پیکربندی، مسیرها و سرویس‌های نسخه قبل حذف می‌شود تا درخواست بعدی
     * (مرحله تکمیل) با تنظیمات و مسیرهای نسخه جدید بالا بیاید.
     */
    private function clearBootCaches(): void
    {
        if (realpath($this->target()) !== realpath(base_path())) {
            return;
        }

        $app = app();
        foreach ([$app->getCachedConfigPath(), $app->getCachedRoutesPath(), $app->getCachedEventsPath(), $app->getCachedServicesPath(), $app->getCachedPackagesPath()] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function resetOpcache(): void
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }

    /**
     * نسخه‌های پشتیبان فایل فقط برای چند به‌روزرسانی آخر نگه داشته می‌شود.
     */
    private function prune(): void
    {
        $keep = SystemUpdate::latest('id')->limit(self::KEEP_ROLLBACKS)->pluck('id')->all();

        foreach (glob($this->directory() . '/{rollback,manifest,manifest-before}-*.{zip,json}', GLOB_BRACE) ?: [] as $file) {
            if (preg_match('/-(\d+)\.(zip|json)$/', $file, $match) && ! in_array((int) $match[1], $keep, true)) {
                @unlink($file);
            }
        }
    }

    private function summary(UpdatePackage $package, array $plan): array
    {
        return [
            'changed' => count($plan['changed']),
            'added' => count($plan['added']),
            'removed' => count($plan['removed']),
            'modified_kept' => array_slice($plan['modified_kept'], 0, 50),
            'unchanged' => $plan['unchanged'],
            'new_migrations' => $plan['new_migrations'],
            'package_sha256' => $package->sha256(),
            'built_at' => $package->builtAt(),
            'commit' => $package->commit(),
            'vendor_included' => $package->vendorIncluded(),
        ];
    }

    /**
     * @return list<string>
     */
    private function notWritable(array $plan): array
    {
        $target = $this->target();
        $failed = [];

        foreach ([...$plan['changed'], ...$plan['removed']] as $path) {
            if (! is_writable($target . '/' . $path) || ! is_writable(dirname($target . '/' . $path))) {
                $failed[] = $path;
            }
        }

        foreach (array_unique(array_map('dirname', $plan['added'])) as $directory) {
            $existing = $target . '/' . $directory;
            while (! is_dir($existing) && $existing !== $target) {
                $existing = dirname($existing);
            }

            if (! is_writable($existing)) {
                $failed[] = $directory . '/';
            }
        }

        return array_values(array_unique($failed));
    }

    /**
     * @return list<string>
     */
    private function ranMigrations(): array
    {
        try {
            $migrator = app('migrator');

            return $migrator->repositoryExists() ? $migrator->getRepository()->getRan() : [];
        } catch (Throwable) {
            return [];
        }
    }

    private function installedManifest(): array
    {
        return json_decode((string) @file_get_contents($this->path(self::INSTALLED_MANIFEST)), true) ?: [];
    }

    private function canBackup(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private function path(string $name): string
    {
        return $this->directory() . '/' . $name;
    }

    private function megabytes(int $bytes): string
    {
        return number_format($bytes / 1048576, 1) . ' MB';
    }
}

<?php

namespace App\Services\Backup;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * پشتیبان پایگاه داده بدون نیاز به mysqldump یا SSH (مرحله ۹۴).
 *
 * خروجی: فایل SQL فشرده (gzip) در storage/app/backups (خارج از public)،
 * قابل بازیابی با Import در phpMyAdmin یا `mysql < file`. ساختار همه جداول
 * و داده همه به‌جز جداول موقت (نشست، کش) ذخیره می‌شود. فقط MySQL/MariaDB.
 */
class DatabaseBackup
{
    /** جداولی که فقط ساختارشان ذخیره می‌شود */
    public const SKIP_DATA = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];

    private const ROWS_PER_INSERT = 200;

    public function directory(): string
    {
        return storage_path('app/backups');
    }

    /**
     * @return string مسیر فایل ساخته‌شده
     */
    public function create(): string
    {
        $connection = DB::connection();

        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Database backup supports only MySQL/MariaDB.');
        }

        File::ensureDirectoryExists($this->directory(), 0750);
        $this->protectDirectory();

        $name = 'idealab-' . now()->format('Ymd-His') . '.sql.gz';
        $temp = $this->directory() . '/.' . $name . '.part';
        $gz = gzopen($temp, 'wb6');

        $write = fn (string $sql) => gzwrite($gz, $sql);
        $pdo = $connection->getPdo();

        $write("-- IdeaLab database backup\n-- Created: " . now()->toIso8601String() . "\n-- Server: " . $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION) . "\n\n");
        $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\nSET time_zone='+00:00';\n\n");

        try {
            foreach ($this->tables() as $table) {
                $create = (array) $connection->selectOne('SHOW CREATE TABLE `' . $table . '`');
                $write("DROP TABLE IF EXISTS `{$table}`;\n" . array_values($create)[1] . ";\n\n");

                if (in_array($table, self::SKIP_DATA, true)) {
                    continue;
                }

                $batch = [];
                foreach ($connection->table($table)->cursor() as $row) {
                    $batch[] = '(' . implode(',', array_map(fn ($value) => $value === null ? 'NULL' : $pdo->quote((string) $value), (array) $row)) . ')';

                    if (count($batch) === self::ROWS_PER_INSERT) {
                        $write($this->insert($table, $row, $batch));
                        $batch = [];
                    }
                }

                if ($batch) {
                    $write($this->insert($table, $row, $batch));
                }
            }

            $write("SET FOREIGN_KEY_CHECKS=1;\n-- End of backup\n");
        } finally {
            gzclose($gz);
        }

        $path = $this->directory() . '/' . $name;
        File::move($temp, $path);

        return $path;
    }

    /**
     * فایل‌های پشتیبان، جدیدترین اول.
     *
     * @return Collection<int, array{name: string, size: int, created_at: Carbon}>
     */
    public function list(): Collection
    {
        if (! File::isDirectory($this->directory())) {
            return collect();
        }

        return collect(File::files($this->directory()))
            ->filter(fn ($file) => str_ends_with($file->getFilename(), '.sql.gz'))
            ->map(fn ($file) => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'created_at' => Carbon::createFromTimestamp($file->getMTime()),
            ])
            ->sortByDesc('name')
            ->values();
    }

    /**
     * فقط نام فایل معتبر داخل پوشه پشتیبان (جلوگیری از پیمایش مسیر).
     */
    public function path(string $name): ?string
    {
        if (! preg_match('/^idealab-\d{8}-\d{6}\.sql\.gz$/', $name)) {
            return null;
        }

        $path = $this->directory() . '/' . $name;

        return File::exists($path) ? $path : null;
    }

    /**
     * @return int تعداد فایل حذف‌شده
     */
    public function prune(int $keep): int
    {
        $old = $this->list()->slice($keep);
        $old->each(fn ($file) => File::delete($this->directory() . '/' . $file['name']));

        return $old->count();
    }

    /**
     * @return list<string>
     */
    private function tables(): array
    {
        return collect(DB::select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']))
            ->map(fn ($row) => array_values((array) $row)[0])
            ->sort()
            ->values()
            ->all();
    }

    private function insert(string $table, object $row, array $values): string
    {
        $columns = implode(',', array_map(fn ($c) => "`{$c}`", array_keys((array) $row)));

        return "INSERT INTO `{$table}` ({$columns}) VALUES\n" . implode(",\n", $values) . ";\n";
    }

    private function protectDirectory(): void
    {
        $htaccess = $this->directory() . '/.htaccess';

        if (! File::exists($htaccess)) {
            File::put($htaccess, "Require all denied\n");
        }
    }
}

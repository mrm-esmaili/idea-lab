<?php

namespace App\Services\Updates;

use App\Support\AppVersion;
use FilesystemIterator;
use InvalidArgumentException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * ساخت بسته نصب/به‌روزرسانی (مرحله ۱۰۲): همه فایل‌ها به‌همراه manifest.json
 * شامل SHA-256 هر فایل، تا نصب‌کننده سالم بودن و دست‌نخوردگی بسته را بسنجد.
 */
class ReleaseBuilder
{
    public const MANIFEST = 'manifest.json';

    /**
     * @return array<string, mixed> manifest
     */
    public function build(string $source, string $version, string $output, bool $includeVendor = true, ?string $commit = null): array
    {
        if (! preg_match(AppVersion::PATTERN, $version)) {
            throw new InvalidArgumentException('Version must look like 1.2.3 or 1.2.3-beta1.');
        }

        $source = rtrim(realpath($source) ?: throw new InvalidArgumentException('Source directory not found.'), '/');

        if (! is_file($source . '/composer.lock')) {
            throw new InvalidArgumentException('composer.lock not found in source.');
        }

        if ($includeVendor && ! is_file($source . '/vendor/autoload.php')) {
            throw new InvalidArgumentException('vendor/ is missing; run composer install --no-dev first or build a lite package.');
        }

        $files = [];
        foreach ($this->files($source, $output) as $path) {
            if (PackagePaths::shouldPackage($path, $includeVendor)) {
                $files[$path] = hash_file('sha256', $source . '/' . $path);
            }
        }

        $versionFile = $version . "\n";
        $files['VERSION'] = hash('sha256', $versionFile);
        ksort($files, SORT_STRING);

        $manifest = [
            'name' => 'idea-lab',
            'version' => $version,
            'built_at' => now()->toIso8601String(),
            'commit' => $commit,
            'min_php' => $this->minPhp($source),
            'vendor_included' => $includeVendor,
            'composer_lock_sha256' => hash_file('sha256', $source . '/composer.lock'),
            'migrations' => $this->migrations($source),
            'files' => $files,
        ];

        if (! is_dir(dirname($output))) {
            mkdir(dirname($output), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($output, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create ' . $output);
        }

        foreach (array_keys($files) as $path) {
            $path === 'VERSION'
                ? $zip->addFromString('VERSION', $versionFile)
                : $zip->addFile($source . '/' . $path, $path);
        }

        $zip->addFromString(self::MANIFEST, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        if (! $zip->close()) {
            throw new RuntimeException('Writing ' . $output . ' failed.');
        }

        return $manifest;
    }

    /**
     * @return \Generator<string> مسیرهای نسبی، بدون پیوند نمادین
     */
    private function files(string $source, string $output): \Generator
    {
        $output = realpath(dirname($output)) . '/' . basename($output);
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isLink() || ! $file->isFile() || $file->getPathname() === $output) {
                continue;
            }

            yield str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
        }
    }

    private function minPhp(string $source): string
    {
        $composer = json_decode((string) file_get_contents($source . '/composer.json'), true);
        preg_match('/(\d+)\.(\d+)/', (string) ($composer['require']['php'] ?? ''), $match);

        return $match ? "{$match[1]}.{$match[2]}.0" : '8.2.0';
    }

    /**
     * @return list<string>
     */
    private function migrations(string $source): array
    {
        $names = array_map(fn ($file) => basename($file, '.php'), glob($source . '/database/migrations/*.php') ?: []);
        sort($names);

        return $names;
    }
}

<?php

namespace App\Services\Updates;

/**
 * قواعد مشترک ساخت بسته و نصب آن: چه چیزی در بسته می‌آید و نصب‌کننده به چه
 * چیزی هرگز دست نمی‌زند.
 */
final class PackagePaths
{
    /** داده سرور؛ نصب‌کننده هرگز در این مسیرها نمی‌نویسد یا حذف نمی‌کند. */
    public const PROTECTED = ['storage/', 'bootstrap/cache/', 'public/uploads/'];

    /** هرگز در بسته نمی‌آید. */
    private const EXCLUDED_DIRS = ['.git/', '.github/', 'tests/', 'dist/', 'node_modules/', '.idea/', '.vscode/'];

    private const EXCLUDED_FILES = ['phpunit.xml', '.phpunit.result.cache', 'public/hot', 'public/storage', 'manifest.json', 'VERSION'];

    /** از پوشه‌های داده فقط اسکلت (برای نصب تازه) در بسته می‌آید. */
    private const SKELETON_FILE = '.gitignore';

    public static function isSafe(string $path): bool
    {
        if ($path === '' || str_starts_with($path, '/') || str_contains($path, '\\') || str_contains($path, "\0") || str_contains($path, ':')) {
            return false;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }

    public static function isProtected(string $path): bool
    {
        if (self::isEnvFile($path)) {
            return true;
        }

        foreach (self::PROTECTED as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    public static function shouldPackage(string $path, bool $includeVendor): bool
    {
        if (self::isEnvFile($path) || in_array($path, self::EXCLUDED_FILES, true)) {
            return false;
        }

        if (! $includeVendor && str_starts_with($path, 'vendor/')) {
            return false;
        }

        foreach (self::EXCLUDED_DIRS as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        foreach (self::PROTECTED as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return basename($path) === self::SKELETON_FILE;
            }
        }

        return true;
    }

    private static function isEnvFile(string $path): bool
    {
        return $path === '.env' || (str_starts_with($path, '.env.') && $path !== '.env.example');
    }
}

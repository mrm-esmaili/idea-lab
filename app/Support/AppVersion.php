<?php

namespace App\Support;

/**
 * نسخه کد از فایل VERSION ریشه پروژه؛ هر بار از دیسک خوانده می‌شود تا پس از
 * به‌روزرسانی، کش پیکربندی نسخه قدیمی را نشان ندهد. نسخه ۱.۰ این فایل را نداشت.
 */
class AppVersion
{
    public const PATTERN = '/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/';

    public const UNVERSIONED = '1.0.0';

    public static function current(): string
    {
        return self::read(base_path());
    }

    public static function read(string $root): string
    {
        $file = rtrim($root, '/') . '/VERSION';
        $version = is_file($file) ? trim((string) file_get_contents($file)) : '';

        return preg_match(self::PATTERN, $version) ? $version : self::UNVERSIONED;
    }
}

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

    /**
     * ورودی دستی نسخه (فرم GitHub Actions) ← قالب استاندارد، یا null. ارقام
     * فارسی و عربی، «v» ابتدایی، فاصله، نویسه‌های نامرئی صفحه‌کلید فارسی (RLM،
     * نیم‌فاصله) و ممیز و خط تیره‌های یونیکد پذیرفته و یکسان می‌شوند.
     */
    public static function normalize(string $input): ?string
    {
        $value = strtr($input, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.', '/' => '.', '‐' => '-', '‑' => '-', '‒' => '-', '–' => '-', '—' => '-', '−' => '-', '_' => '-',
        ]);
        $value = preg_replace('/[\s\p{Cf}]+/u', '', $value);
        $value = preg_replace('/^v/i', '', $value);
        // «1.1» یعنی 1.1.0
        $value = preg_replace('/^(\d+\.\d+)(-[0-9A-Za-z.]+)?$/', '$1.0$2', $value);

        return preg_match(self::PATTERN, $value) ? $value : null;
    }

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

<?php

namespace App\Services\Updates;

/**
 * cPanel (MultiPHP) نسخه PHP را با بلوکی در .htaccess تعیین می‌کند؛ اگر نسخه
 * جدید .htaccess بدون آن بلوک نوشته شود، سایت با PHP دیگری اجرا می‌شود.
 */
final class Htaccess
{
    private const BLOCK = '/^# php -- BEGIN cPanel-generated handler, do not edit$.*?^# php -- END cPanel-generated handler, do not edit$/ms';

    public static function merge(string $new, ?string $current): string
    {
        if ($current === null || ! preg_match(self::BLOCK, $current, $match) || str_contains($new, $match[0])) {
            return $new;
        }

        return rtrim($new) . "\n\n" . $match[0] . "\n";
    }
}

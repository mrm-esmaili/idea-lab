<?php

namespace App\Support;

/**
 * تبدیل و نمایش مبالغ.
 *
 * قرارداد: همه مبالغ در پایگاه داده به ریال (ستون‌های *_rial) ذخیره
 * می‌شوند و ورود و نمایش همیشه به تومان است. تبدیل فقط از طریق این
 * کلاس انجام شود تا تبدیل دوباره رخ ندهد.
 */
class Money
{
    public const RIAL_PER_TOMAN = 10;

    public static function rialToToman(int $rial): int
    {
        return intdiv($rial, self::RIAL_PER_TOMAN);
    }

    public static function tomanToRial(int $toman): int
    {
        return $toman * self::RIAL_PER_TOMAN;
    }

    public static function formatRialAsToman(?int $rial): string
    {
        if ($rial === null) {
            return '—';
        }

        return number_format(self::rialToToman($rial)) . ' تومان';
    }

    /**
     * ورودی فرم (مثلاً «۱۲۰٬۰۰۰» یا «120,000») را به رشته عددی لاتین
     * تبدیل می‌کند تا اعتبارسنجی integer روی آن انجام شود.
     */
    public static function normalizeInput(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $value = preg_replace('/[\s,٬،]/u', '', $value);

        return $value === '' ? null : $value;
    }
}

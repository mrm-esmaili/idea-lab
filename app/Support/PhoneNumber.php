<?php

namespace App\Support;

class PhoneNumber
{
    /**
     * شماره موبایل ایران را به قالب یکتای 09xxxxxxxxx تبدیل می‌کند.
     * برای ورودی نامعتبر null برمی‌گرداند.
     */
    public static function normalizeMobile(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        $hasPlus = str_starts_with(trim($value), '+');

        $digits = preg_replace('/\D+/', '', $value);

        if ($hasPlus || str_starts_with($digits, '0098')) {
            $digits = preg_replace('/^(00)?98/', '', $digits);
        } elseif (strlen($digits) === 12 && str_starts_with($digits, '98')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0' . $digits;
        }

        return preg_match('/^09\d{9}$/', $digits) ? $digits : null;
    }
}

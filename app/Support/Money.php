<?php

namespace App\Support;

/**
 * تبدیل و نمایش مبالغ.
 *
 * قرارداد: مبلغ پرداخت‌ها (payments.amount_rial) به ریال ذخیره می‌شود و
 * همه نمایش‌ها به تومان است. تبدیل فقط از طریق این کلاس انجام شود.
 */
class Money
{
    public static function rialToToman(int $rial): int
    {
        return intdiv($rial, 10);
    }

    public static function tomanToRial(int $toman): int
    {
        return $toman * 10;
    }

    public static function formatRialAsToman(int $rial): string
    {
        return number_format(self::rialToToman($rial)) . ' تومان';
    }
}

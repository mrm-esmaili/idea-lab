<?php

namespace App\Support;

/**
 * تخمین تعداد بخش‌های پیامک: متن فارسی (UCS-2) ۷۰ نویسه و در چندبخشی ۶۷
 * نویسه در هر بخش؛ متن فقط لاتین ۱۶۰ و ۱۵۳.
 */
class SmsText
{
    public static function parts(string $text): int
    {
        $length = mb_strlen($text);

        if ($length === 0) {
            return 0;
        }

        $unicode = preg_match('/[^\x00-\x7F]/', $text) === 1;
        [$single, $multi] = $unicode ? [70, 67] : [160, 153];

        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}

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

    /**
     * مقدار پارامتر قالب در حد مجاز SMS.ir (۲۵ نویسه)؛ مقدار بلندتر با «…»
     * کوتاه می‌شود تا کل پیامک رد نشود. ۰ یعنی بدون محدودیت.
     */
    public static function fit(string $value, ?int $max = null): string
    {
        $max ??= (int) config('sms.template_param_max', 25);
        $value = trim(preg_replace('/\s+/u', ' ', $value));

        if ($max <= 0 || mb_strlen($value) <= $max) {
            return $value;
        }

        return rtrim(mb_substr($value, 0, $max - 1)) . '…';
    }

    /**
     * نشانی کوتاه برای پیامک: بدون https:// (گوشی‌ها دامنه را خودکار لینک می‌کنند).
     */
    public static function shortUrl(string $url): string
    {
        return preg_replace('#^https?://(www\.)?#i', '', rtrim($url, '/'));
    }
}

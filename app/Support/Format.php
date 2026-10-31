<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

class Format
{
    /**
     * تاریخ و ساعت شمسی به وقت نمایش؛ قالب با نشانه‌های میلادی (Y-m-d H:i)
     * داده می‌شود و خروجی شمسی با جداکننده «/» است.
     */
    public static function datetime(
        ?DateTimeInterface $value,
        string $format = 'Y-m-d H:i'
    ): string {
        if ($value === null) {
            return '—';
        }

        return Jalali::format($value, str_replace('-', '/', $format));
    }

    /**
     * اعداد فارسی برای متن نمایشی صفحات عمومی (مرحله ۱۲۵): ارقام، جداکننده
     * هزارگان و درصد کنار عدد. برای نشانی، مقدار فیلد فرم و لینک تلفن استفاده نشود.
     */
    public static function faDigits(?string $value): string
    {
        $value = (string) $value;
        $value = preg_replace(['/(?<=\d),(?=\d{3})/', '/(?<=\d)\s?%/'], ['٬', '٪'], $value);

        return strtr($value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
    }

    /**
     * متن لاتین (نسخه، نام فایل) داخل جمله فارسی، بدون جابه‌جا شدن اجزایش؛
     * برای جاهایی مثل عنوان صفحه یا پیام که HTML (bdi) پذیرفته نمی‌شود.
     */
    public static function ltr(string $value): string
    {
        return "\u{2066}{$value}\u{2069}";
    }

    public static function date(?DateTimeInterface $value): string
    {
        return self::datetime($value, 'Y-m-d');
    }

    /**
     * تاریخ میلادی برای ورودی‌های فرم و تبادل داده.
     */
    public static function gregorian(?DateTimeInterface $value, string $format = 'Y-m-d H:i'): string
    {
        return $value === null
            ? '—'
            : Carbon::instance($value)->timezone(config('app.display_timezone'))->format($format);
    }

    public static function status(string $group, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return config("statuses.{$group}.{$value}", $value);
    }

    /**
     * نمایش مقدار شاخص بر اساس واحد آن در App\Services\ExperimentMetrics::METRICS.
     */
    public static function metric(string $key, int|float|null $value): string
    {
        if ($value === null) {
            return '—';
        }

        return match (\App\Services\ExperimentMetrics::METRICS[$key][1] ?? 'count') {
            'rial' => Money::formatRialAsToman((int) $value),
            'percent' => number_format((float) $value, 2) . '%',
            default => number_format((float) $value, floor((float) $value) == $value ? 0 : 2),
        };
    }
}

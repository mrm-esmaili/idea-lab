<?php

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;

class Format
{
    public static function datetime(
        ?DateTimeInterface $value,
        string $format = 'Y-m-d H:i'
    ): string {
        if ($value === null) {
            return '—';
        }

        return Carbon::instance($value)
            ->timezone(config('app.display_timezone'))
            ->format($format);
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

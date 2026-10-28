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
}

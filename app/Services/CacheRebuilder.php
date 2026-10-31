<?php

namespace App\Services;

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Facade;

/**
 * پاک کردن و ساخت دوباره کش تنظیمات، مسیرها و نماها از داخل درخواست وب.
 */
class CacheRebuilder
{
    public function rebuild(bool $cache): void
    {
        $app = app();

        try {
            Artisan::call('optimize:clear');

            if ($cache) {
                Artisan::call('optimize');
            }
        } finally {
            // config:cache برای خواندن تنظیمات یک نمونه تازه از برنامه می‌سازد و
            // آن را نمونه سراسری و ریشه Facadeها می‌کند؛ بقیه همین درخواست
            // (نشست، پیام موفقیت، ممیزی) باید روی نمونه اصلی اجرا شود
            Container::setInstance($app);
            Facade::clearResolvedInstances();
            Facade::setFacadeApplication($app);
        }
    }
}

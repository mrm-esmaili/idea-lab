<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\CacheRebuilder;
use App\Services\SystemStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/**
 * وضعیت سیستم و عملیات نگه‌داری برای هاست بدون SSH (مراحل ۸۸، ۹۵ و ۹۶).
 */
class SystemStatusController extends Controller
{
    public function __invoke(SystemStatus $status)
    {
        $checks = collect($status->checks());

        return view('admin.system.index', [
            'groups' => $checks->groupBy('group'),
            'counts' => $checks->countBy('status'),
            'configCached' => app()->configurationIsCached(),
        ]);
    }

    /**
     * اجرای migrationهای جدید پس از به‌روزرسانی کد.
     */
    public function migrate(Request $request, AuditLogger $audit)
    {
        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['system' => 'اجرای migration ناموفق بود؛ جزئیات در لاگ سرور ثبت شد. پیش از تلاش دوباره از پایگاه داده پشتیبان بگیرید.']);
        }

        $output = trim(Artisan::output());
        $audit->log($request->user(), 'system.migrated', ['output' => mb_substr($output, 0, 2000)]);

        return back()->with('success', 'migrationها اجرا شد. ' . (str_contains($output, 'Nothing to migrate') ? 'مورد جدیدی نبود.' : ''));
    }

    /**
     * ساخت دوباره کش تنظیمات، مسیرها و نماها (پس از تغییر .env یا به‌روزرسانی کد).
     */
    public function optimize(Request $request, AuditLogger $audit, CacheRebuilder $cache)
    {
        $cache->rebuild($request->boolean('cache'));

        $audit->log($request->user(), 'system.optimized', ['cache' => $request->boolean('cache')]);

        return back()->with('success', $request->boolean('cache') ? 'کش‌ها دوباره ساخته شد.' : 'کش‌ها پاک شد.');
    }
}

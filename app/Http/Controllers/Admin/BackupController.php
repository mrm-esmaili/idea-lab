<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * پشتیبان‌ها برای مالک بدون SSH؛ فایل‌ها شامل داده شخصی‌اند و هر دریافت ثبت می‌شود.
 */
class BackupController extends Controller
{
    public function index(DatabaseBackup $backup)
    {
        return view('admin.backups.index', [
            'files' => $backup->list(),
            'keep' => config('backup.keep'),
        ]);
    }

    public function store(Request $request, DatabaseBackup $backup, AuditLogger $audit)
    {
        // جلوگیری از اجرای هم‌زمان دو پشتیبان سنگین
        $lock = Cache::lock('backup:database', 600);

        if (! $lock->get()) {
            return back()->withErrors(['backup' => 'پشتیبان دیگری در حال ساخت است.']);
        }

        try {
            $path = $backup->create();
            $backup->prune((int) config('backup.keep'));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => 'ساخت پشتیبان ناموفق بود؛ جزئیات در لاگ سرور ثبت شد.']);
        } finally {
            $lock->release();
        }

        $audit->log($request->user(), 'backup.created', ['file' => basename($path)]);

        return back()->with('success', 'پشتیبان ' . basename($path) . ' ساخته شد.');
    }

    public function download(Request $request, string $name, DatabaseBackup $backup, AuditLogger $audit)
    {
        $path = $backup->path($name);
        abort_unless($path, 404);

        $audit->log($request->user(), 'backup.downloaded', ['file' => $name]);

        return response()->download($path, $name, ['Content-Type' => 'application/gzip']);
    }
}

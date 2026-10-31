<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemUpdate;
use App\Services\AuditLogger;
use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdateInstaller;
use App\Services\Updates\UpdatePackage;
use App\Support\Format;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;

/**
 * به‌روزرسانی برنامه از پنل (مرحله ۱۰۷)؛ فقط مدیر کل، با تأیید رمز.
 * مسیرهای این بخش در حالت تعمیر هم در دسترس‌اند (bootstrap/app.php) و
 * نام و رفتارشان باید در نسخه‌های بعد ثابت بماند.
 */
class UpdateController extends Controller
{
    public function __construct(private readonly UpdateInstaller $installer)
    {
    }

    public function index()
    {
        $enabled = (bool) config('updates.enabled');

        return view('admin.updates.index', [
            'enabled' => $enabled,
            'version' => $this->installer->currentVersion(),
            'packages' => $enabled ? $this->installer->packages() : [],
            'updates' => SystemUpdate::with('user')->latest('id')->paginate(15),
            'pending' => SystemUpdate::whereIn('status', [SystemUpdate::APPLYING, SystemUpdate::FILES_APPLIED])->latest('id')->first(),
            'uploadLimit' => $this->uploadLimitMb(),
            'directory' => str_replace(base_path() . '/', '', config('updates.directory')),
        ]);
    }

    public function upload(Request $request, AuditLogger $audit)
    {
        $this->ensureEnabled();

        $request->validate([
            'package' => ['required', 'file', 'max:' . (config('updates.max_upload_mb') * 1024)],
        ], [
            'package.uploaded' => 'بارگذاری ناموفق بود؛ احتمالاً حجم فایل از حد مجاز هاست (' . $this->uploadLimitMb() . ' مگابایت) بیشتر است. فایل را با File Manager در پوشه بسته‌ها قرار دهید.',
        ]);

        $file = $request->file('package');
        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            return back()->withErrors(['package' => 'فایل باید zip باشد.']);
        }

        try {
            $bundle = UpdatePackage::open($file->getRealPath());
        } catch (UpdateException $e) {
            return back()->withErrors(['package' => $e->getMessage()]);
        }

        $name = 'idea-lab-' . $bundle->version() . ($bundle->vendorIncluded() ? '' : '-lite') . '.zip';
        unset($bundle);
        $file->move($this->installer->directory(), $name);

        $audit->log($request->user(), 'system.update_uploaded', ['package' => $name, 'sha256' => hash_file('sha256', $this->installer->directory() . '/' . $name)]);

        return redirect()->route('admin.updates.review', $name);
    }

    public function review(Request $request, string $package)
    {
        $this->ensureEnabled();
        $path = $this->installer->packagePath($package) ?? abort(404);

        try {
            $bundle = UpdatePackage::open($path);
        } catch (UpdateException $e) {
            return redirect()->route('admin.updates.index')->withErrors(['package' => $package . ': ' . $e->getMessage()]);
        }

        $plan = $this->installer->plan($bundle);
        $reinstall = $request->boolean('reinstall');

        return view('admin.updates.review', [
            'name' => $package,
            'bundle' => $bundle,
            'plan' => $plan,
            'checks' => $this->installer->checks($bundle, $plan, $reinstall),
            'reinstall' => $reinstall,
            'current' => $this->installer->currentVersion(),
        ]);
    }

    public function apply(Request $request, string $package, AuditLogger $audit)
    {
        $this->ensureEnabled();
        $path = $this->installer->packagePath($package) ?? abort(404);

        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'رمز عبور درست نیست.']);
        }

        try {
            $update = $this->installer->apply(UpdatePackage::open($path), $request->user(), 'panel', $request->boolean('reinstall'));
        } catch (UpdateException $e) {
            return back()->withErrors(['update' => $e->getMessage()]);
        }

        $audit->log($update, 'system.update_applied', ['package' => $package, 'from' => $update->from_version, 'to' => $update->to_version], $request->user());

        // تکمیل در درخواست بعدی و با کد جدید اجرا می‌شود
        return redirect()->route('admin.updates.show', $update)->with('auto_finish', true);
    }

    public function show(SystemUpdate $update)
    {
        return view('admin.updates.show', [
            'update' => $update->load('user'),
            'canRollback' => $this->installer->canRollback($update),
        ]);
    }

    public function finish(Request $request, SystemUpdate $update, AuditLogger $audit)
    {
        try {
            $this->installer->finish($update);
        } catch (UpdateException $e) {
            $audit->log($update, 'system.update_failed', ['error' => mb_substr($e->getMessage(), 0, 500)], $request->user());

            return redirect()->route('admin.updates.show', $update)->withErrors(['update' => $e->getMessage()]);
        }

        $audit->log($update, 'system.update_completed', ['to' => $update->to_version], $request->user());

        return redirect()->route('admin.updates.show', $update)->with('success', 'نسخه ' . Format::ltr($update->to_version) . ' نصب شد.');
    }

    public function rollback(Request $request, SystemUpdate $update, AuditLogger $audit)
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return back()->withErrors(['password' => 'رمز عبور درست نیست.']);
        }

        try {
            $this->installer->rollback($update);
        } catch (UpdateException $e) {
            return back()->withErrors(['update' => $e->getMessage()]);
        }

        $audit->log($update, 'system.update_rolled_back', ['to' => $update->from_version], $request->user());

        return redirect()->route('admin.updates.show', $update)->with('success', 'کد به نسخه ' . Format::ltr((string) $update->from_version) . ' برگردانده شد. پایگاه داده تغییری نکرد.');
    }

    public function destroyPackage(Request $request, string $package, AuditLogger $audit)
    {
        $this->ensureEnabled();
        $path = $this->installer->packagePath($package) ?? abort(404);

        File::delete($path);
        $audit->log($request->user(), 'system.update_package_deleted', ['package' => $package]);

        return redirect()->route('admin.updates.index')->with('success', 'بسته ' . Format::ltr($package) . ' حذف شد.');
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('updates.enabled'), 404);
    }

    private function uploadLimitMb(): int
    {
        $bytes = fn (string $value) => (int) $value * match (strtolower(substr(trim($value), -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        $limits = array_filter([
            $bytes((string) ini_get('upload_max_filesize')),
            $bytes((string) ini_get('post_max_size')),
            config('updates.max_upload_mb') * 1024 ** 2,
        ]);

        return (int) floor(min($limits) / 1024 ** 2);
    }
}

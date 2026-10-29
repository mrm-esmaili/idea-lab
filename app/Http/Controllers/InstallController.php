<?php

namespace App\Http\Controllers;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\Rules\Password;
use Throwable;

/**
 * نصب اولیه روی cPanel بدون SSH (مرحله ۹۵): اجرای migrationها و ساخت اولین
 * مدیر کل. فقط وقتی فعال است که INSTALL_TOKEN در .env تنظیم شده، توکن درست
 * در نشانی آمده و هنوز هیچ مدیر کلی وجود ندارد؛ در غیر این صورت 404.
 * پس از نصب INSTALL_TOKEN را از .env حذف کنید.
 *
 * این مسیرها بدون نشست، کوکی و CSRF اجرا می‌شوند (پیش از migration جدول نشست
 * و کش وجود ندارد)؛ توکن محرمانه نقش محافظ CSRF را دارد.
 */
class InstallController extends Controller
{
    public function show(Request $request)
    {
        $this->authorizeInstall($request);

        return $this->form($request);
    }

    public function store(Request $request)
    {
        $this->authorizeInstall($request);

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ], [], ['name' => 'نام', 'email' => 'ایمیل', 'password' => 'رمز عبور']);

        if ($validator->fails()) {
            return $this->form($request, $validator->errors());
        }

        try {
            Artisan::call('migrate', ['--force' => true]);
        } catch (Throwable $e) {
            Log::error('Install migration failed.', ['exception' => $e->getMessage()]);

            return $this->form($request, new MessageBag(['install' => 'اجرای migration ناموفق بود؛ تنظیمات پایگاه داده در .env را بررسی کنید. جزئیات در storage/logs ثبت شد.']));
        }

        $data = $validator->validated();

        User::updateOrCreate(['email' => $data['email']], [
            'name' => $data['name'],
            'password' => $data['password'],
            'role' => AdminRole::SuperAdmin,
        ]);

        Log::info('Installation completed.', ['email' => $data['email'], 'ip' => $request->ip()]);

        return view('install', ['done' => true]);
    }

    /**
     * بدون نشست (جدول نشست پیش از migration وجود ندارد): خطاها مستقیم به نما داده می‌شوند.
     */
    private function form(Request $request, ?MessageBag $errors = null)
    {
        return response()->view('install', [
            'done' => false,
            'token' => (string) $request->input('token', $request->query('token')),
            'old' => $request->only('name', 'email'),
            'errors' => (new ViewErrorBag)->put('default', $errors ?? new MessageBag),
        ], $errors ? 422 : 200);
    }

    private function authorizeInstall(Request $request): void
    {
        $token = (string) config('app.install_token');

        abort_if($token === '' || ! hash_equals($token, (string) $request->input('token', $request->query('token'))), 404);

        try {
            $installed = User::where('role', AdminRole::SuperAdmin->value)->exists();
        } catch (Throwable) {
            // جدول کاربران هنوز ساخته نشده
            $installed = false;
        }

        abort_if($installed, 404);
    }
}

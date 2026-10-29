<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * محافظت فرم‌های عمومی از ارسال خودکار (مرحله ۹۲)، علاوه بر محدودیت نرخ هر IP:
 *
 * - honeypot: فیلد پنهان؛ اگر پر شود ارسال بی‌صدا نادیده گرفته می‌شود و
 *   ربات پاسخ موفق ظاهری می‌گیرد تا روش دیگری امتحان نکند.
 * - زمان پر کردن: زمان نمایش فرم رمزنگاری‌شده در فرم قرار می‌گیرد؛ ارسال
 *   زودتر از چند ثانیه، فرم قدیمی یا مقدار دست‌کاری‌شده پذیرفته نمی‌شود.
 *
 * فیلدها با Blade component <x-public-form-guard /> در فرم قرار می‌گیرند.
 */
class GuardPublicForm
{
    public function handle(Request $request, Closure $next): Response
    {
        if (filled($request->input(config('security.honeypot_field')))) {
            Log::info('Public form honeypot triggered.', ['route' => $request->route()?->getName(), 'ip' => $request->ip()]);

            return back()->with('lead_success', $request->route('slug'));
        }

        $minSeconds = (int) config('security.public_form_min_seconds');

        if ($minSeconds > 0) {
            $startedAt = null;

            try {
                $startedAt = (int) Crypt::decryptString((string) $request->input('_form_started'));
            } catch (DecryptException) {
                // فیلد ندارد یا دست‌کاری شده
            }

            $elapsed = $startedAt ? now()->getTimestamp() - $startedAt : null;

            if ($elapsed === null || $elapsed < $minSeconds || $elapsed > config('security.public_form_max_age_hours') * 3600) {
                return back()
                    ->withInput($request->except(config('security.honeypot_field'), '_form_started'))
                    ->withErrors(['form' => $elapsed !== null && $elapsed < $minSeconds
                        ? 'ارسال خیلی سریع بود؛ لطفاً چند ثانیه دیگر دوباره ارسال کنید.'
                        : 'فرم منقضی شده است؛ صفحه را دوباره بارگذاری کنید.'], $request->input('form') === 'preorder' ? 'preorder' : 'lead');
            }
        }

        return $next($request);
    }
}

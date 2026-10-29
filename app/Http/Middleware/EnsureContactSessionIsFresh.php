<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * عمر نشست مخاطب از زمان ورود محدود است (مرحله ۳۲)، حتی اگر فعال باشد.
 * فقط guard مخاطب خارج می‌شود تا نشست مدیر در همان مرورگر دست نخورد.
 */
class EnsureContactSessionIsFresh
{
    public function handle(Request $request, Closure $next): Response
    {
        $loginAt = $request->session()->get('contact_login_at');
        $maxAge = (int) config('otp.contact_session_hours') * 3600;

        if (! is_int($loginAt) || now()->getTimestamp() - $loginAt > $maxAge) {
            Auth::guard('contact')->logout();
            $request->session()->forget('contact_login_at');

            return redirect()
                ->route('account.login')
                ->with('notice', 'نشست شما به پایان رسید؛ لطفاً دوباره وارد شوید.');
        }

        return $next($request);
    }
}

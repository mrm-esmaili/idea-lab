<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Rules\IranianMobile;
use App\Services\ContactResolver;
use App\Services\Otp\OtpException;
use App\Services\Otp\OtpService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * ورود و ثبت‌نام مخاطب با موبایل و کد یک‌بارمصرف (مرحله ۲۷).
 * ثبت‌نام جداگانه ندارد: اولین ورود موفق، مخاطب را می‌سازد یا به مخاطب
 * موجود (ساخته‌شده از فرم‌ها) وصل می‌شود. هیچ دسترسی مدیریتی نمی‌دهد.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('account.login');
    }

    public function sendCode(Request $request, OtpService $otp)
    {
        $data = $request->validate([
            'mobile' => ['required', 'string', 'max:30', new IranianMobile()],
        ], [], ['mobile' => 'شماره موبایل']);

        $mobile = PhoneNumber::normalizeMobile($data['mobile']);

        try {
            $otp->send($mobile, $request->ip());
        } catch (OtpException $e) {
            // اگر کد قبلی هنوز معتبر است، کاربر همچنان می‌تواند آن را وارد کند
            if ($request->session()->get('otp_mobile') === $mobile) {
                return redirect()->route('account.verify')->withErrors(['code' => $e->getMessage()]);
            }

            throw ValidationException::withMessages(['mobile' => $e->getMessage()]);
        }

        $request->session()->put('otp_mobile', $mobile);
        $request->session()->put('otp_sent_at', now()->getTimestamp());

        return redirect()->route('account.verify');
    }

    public function showVerify(Request $request)
    {
        $mobile = $request->session()->get('otp_mobile');

        if (! $mobile) {
            return redirect()->route('account.login');
        }

        $wait = max(0, (int) config('otp.resend_cooldown') - (now()->getTimestamp() - (int) $request->session()->get('otp_sent_at')));

        return view('account.verify', [
            'mobile' => $mobile,
            'resendWait' => $wait,
        ]);
    }

    public function verify(
        Request $request,
        OtpService $otp,
        ContactResolver $contacts
    ) {
        $mobile = $request->session()->get('otp_mobile');

        if (! $mobile) {
            return redirect()->route('account.login');
        }

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10'],
        ], [], ['code' => 'کد ورود']);

        try {
            $otp->verify($mobile, $data['code']);
        } catch (OtpException $e) {
            throw ValidationException::withMessages(['code' => $e->getMessage()]);
        }

        $contact = $contacts->forMobile($mobile);
        $contact->forceFill(['last_login_at' => now()])->save();

        $request->session()->forget(['otp_mobile', 'otp_sent_at']);

        Auth::guard('contact')->login($contact);
        $request->session()->regenerate();
        $request->session()->put('contact_login_at', now()->getTimestamp());

        return redirect()->intended(route('account.dashboard'));
    }

    public function logout(Request $request)
    {
        // فقط خروج مخاطب؛ نشست احتمالی مدیر در همین مرورگر باطل نمی‌شود
        Auth::guard('contact')->logout();
        $request->session()->forget('contact_login_at');
        $request->session()->regenerateToken();

        return redirect()->route('account.login')->with('notice', 'از حساب خود خارج شدید.');
    }
}

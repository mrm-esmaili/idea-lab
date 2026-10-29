<?php

namespace App\Services\Otp;

use App\Models\OtpCode;
use App\Services\Sms\SmsException;
use App\Services\Sms\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * کد یک‌بارمصرف ورود (مراحل ۲۹ و ۳۰).
 *
 * - کد هرگز ذخیره یا لاگ نمی‌شود؛ فقط HMAC آن با APP_KEY.
 * - انقضا، فاصله ارسال دوباره، سقف ارسال برای هر شماره و هر IP،
 *   سقف تلاش برای هر کد، یک‌بارمصرف بودن، و باطل شدن کد قبلی با
 *   صدور کد جدید.
 */
class OtpService
{
    public function __construct(
        private readonly SmsService $sms,
    ) {}

    /**
     * @throws OtpException
     */
    public function send(string $mobile, ?string $ip, string $purpose = 'login'): void
    {
        $latest = OtpCode::where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        $cooldown = (int) config('otp.resend_cooldown');

        if ($latest && $latest->created_at->gt(now()->subSeconds($cooldown))) {
            $wait = $cooldown - (int) $latest->created_at->diffInSeconds(now());

            throw new OtpException('برای دریافت کد جدید ' . max(1, $wait) . ' ثانیه دیگر صبر کنید.');
        }

        $sentLastHour = OtpCode::where('mobile', $mobile)
            ->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($sentLastHour >= (int) config('otp.max_sends_per_mobile_per_hour')) {
            throw new OtpException('تعداد درخواست کد برای این شماره زیاد است؛ یک ساعت دیگر دوباره تلاش کنید.');
        }

        $ipKey = 'otp-ip:' . ($ip ?? 'unknown');

        if (RateLimiter::tooManyAttempts($ipKey, (int) config('otp.max_sends_per_ip_per_hour'))) {
            throw new OtpException('تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.');
        }

        RateLimiter::hit($ipKey, 3600);

        $code = $this->generateCode();

        $otp = DB::transaction(function () use ($mobile, $purpose, $code, $ip) {
            // کد قبلی با صدور کد جدید باطل می‌شود
            OtpCode::where('mobile', $mobile)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            return OtpCode::create([
                'mobile' => $mobile,
                'purpose' => $purpose,
                'code_hash' => $this->hash($mobile, $code),
                'expires_at' => now()->addSeconds((int) config('otp.ttl')),
                'ip' => $ip,
            ]);
        });

        try {
            $this->sms->sendTemplate('otp', $mobile, ['code' => $code], secret: ['code']);
        } catch (SmsException) {
            // کدی که ارسال نشده قابل استفاده نیست، ولی در سقف ارسال شمرده می‌شود
            $otp->update(['consumed_at' => now()]);

            throw new OtpException('ارسال پیامک کد ورود در حال حاضر ممکن نیست؛ کمی بعد دوباره تلاش کنید.');
        }
    }

    /**
     * @throws OtpException
     */
    public function verify(string $mobile, string $code, string $purpose = 'login'): void
    {
        $code = trim(strtr($code, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        ]));

        $result = DB::transaction(function () use ($mobile, $code, $purpose) {
            $otp = OtpCode::where('mobile', $mobile)
                ->where('purpose', $purpose)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $otp) {
                return 'missing';
            }

            $otp->attempts++;

            if (hash_equals($otp->code_hash, $this->hash($mobile, $code))) {
                $otp->consumed_at = now();
                $otp->save();

                return 'ok';
            }

            $maxAttempts = (int) config('otp.max_attempts');

            if ($otp->attempts >= $maxAttempts) {
                $otp->consumed_at = now();
                $otp->save();

                return 'locked';
            }

            $otp->save();

            return $maxAttempts - $otp->attempts;
        });

        match (true) {
            $result === 'ok' => null,
            $result === 'missing' => throw new OtpException('کد منقضی شده یا معتبر نیست؛ کد جدید دریافت کنید.'),
            $result === 'locked' => throw new OtpException('تعداد تلاش‌های ناموفق زیاد بود؛ کد جدید دریافت کنید.'),
            default => throw new OtpException("کد واردشده درست نیست. {$result} تلاش دیگر باقی مانده است."),
        };
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, 10 ** $length - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $mobile, string $code): string
    {
        return hash_hmac('sha256', $mobile . '|' . $code, (string) config('app.key'));
    }
}

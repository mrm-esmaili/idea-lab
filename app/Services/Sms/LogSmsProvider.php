<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Log;

/**
 * فقط برای توسعه محلی: پیامک را به‌جای ارسال در لاگ می‌نویسد (شامل کد OTP).
 * در محیط production مجاز نیست تا کد ورود هرگز در لاگ سرور نوشته نشود.
 */
class LogSmsProvider implements SmsProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function sendTemplate(string $mobile, int $templateId, array $parameters): array
    {
        $this->ensureAllowed();

        Log::info('SMS (log driver)', [
            'mobile' => $mobile,
            'template_id' => $templateId,
            'parameters' => $parameters,
        ]);

        return ['message_id' => null, 'cost' => null, 'response' => null];
    }

    public function sendText(array $mobiles, string $text): array
    {
        $this->ensureAllowed();

        Log::info('SMS text (log driver)', [
            'mobiles' => $mobiles,
            'text' => $text,
        ]);

        return ['message_ids' => array_fill(0, count($mobiles), null), 'cost' => null, 'response' => null];
    }

    private function ensureAllowed(): void
    {
        if (app()->isProduction()) {
            throw new SmsException('The log SMS driver is not allowed in production; set SMS_DRIVER=smsir.');
        }
    }
}

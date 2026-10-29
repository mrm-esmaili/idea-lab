<?php

namespace App\Services\Sms;

use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use Illuminate\Support\Facades\Log;

/**
 * نقطه واحد ارسال پیامک.
 *
 * - قالب با کلید داخلی (مثلاً otp) از جدول sms_templates خوانده می‌شود؛
 *   شناسه قالب SMS.ir و نام پارامترها از پنل مدیریت قابل تغییر است.
 * - هر ارسال (موفق یا ناموفق) در sms_messages ثبت می‌شود؛ مقدار
 *   پارامترهای محرمانه (مثل کد OTP) ذخیره نمی‌شود.
 */
class SmsService
{
    public function __construct(
        private readonly SmsProvider $provider,
    ) {}

    /**
     * @param  array<string, string|int>  $values  متغیر داخلی => مقدار
     * @param  list<string>  $secret  متغیرهایی که مقدارشان ثبت نشود
     *
     * @throws SmsException
     */
    public function sendTemplate(string $key, string $mobile, array $values, array $secret = []): SmsMessage
    {
        $template = SmsTemplate::where('key', $key)->first();

        if (! $template) {
            throw new SmsException("SMS template [{$key}] does not exist.");
        }

        $parameters = [];
        $logged = [];

        foreach ($template->parameters as $variable => $parameterName) {
            $value = (string) ($values[$variable] ?? '');
            $parameters[$parameterName] = $value;
            $logged[$parameterName] = in_array($variable, $secret, true) ? '***' : $value;
        }

        $message = new SmsMessage([
            'mobile' => $mobile,
            'template_key' => $key,
            'category' => $template->category,
            'provider' => $this->provider->name(),
            'parameters' => $logged,
        ]);

        try {
            if (! $template->isConfigured() && ! $this->provider instanceof LogSmsProvider) {
                throw new SmsException("SMS template [{$key}] has no provider template ID.");
            }

            $result = $this->provider->sendTemplate(
                $mobile,
                (int) $template->provider_template_id,
                $parameters
            );
        } catch (SmsException $e) {
            $message->fill([
                'status' => SmsMessage::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 255),
            ])->save();

            Log::warning('SMS sending failed.', [
                'sms_message_id' => $message->id,
                'template' => $key,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $message->fill([
            'status' => SmsMessage::STATUS_SENT,
            'provider_message_id' => $result['message_id'],
            'cost' => $result['cost'],
        ])->save();

        return $message;
    }
}

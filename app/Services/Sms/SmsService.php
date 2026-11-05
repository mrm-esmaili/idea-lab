<?php

namespace App\Services\Sms;

use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Support\SmsText;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

use function Illuminate\Support\defer;

/**
 * نقطه واحد ارسال پیامک.
 *
 * - قالب با کلید داخلی (مثلاً otp) از جدول sms_templates خوانده می‌شود؛
 *   شناسه قالب SMS.ir و نام پارامترها از پنل مدیریت قابل تغییر است.
 * - کد ورود فوری ارسال می‌شود (sendTemplate)؛ سایر پیامک‌ها در صف ثبت و
 *   پس از پاسخ به کاربر یا با cron توسط SmsDispatcher ارسال می‌شوند.
 * - کلید یکتای dedupe_key از ثبت دوباره یک پیام (مثلاً دو بار اطلاع
 *   پرداخت یک تراکنش) جلوگیری می‌کند.
 * - هر ارسال (موفق یا ناموفق) در sms_messages ثبت می‌شود؛ مقدار
 *   پارامترهای محرمانه (مثل کد OTP) ذخیره نمی‌شود.
 */
class SmsService
{
    public function __construct(
        private readonly SmsProvider $provider,
    ) {}

    /**
     * ارسال فوری.
     *
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

        $message = new SmsMessage([
            'mobile' => $mobile,
            'template_key' => $key,
            'type' => SmsMessage::TYPE_TEMPLATE,
            'category' => $template->category,
            'provider' => $this->provider->name(),
            'attempts' => 1,
        ]);

        try {
            $this->deliverTemplate($template, $message, $values, $secret);
        } catch (SmsException $e) {
            $message->fill([
                'status' => SmsMessage::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 255),
                'response' => $e->response,
            ])->save();

            Log::warning('SMS sending failed.', [
                'sms_message_id' => $message->id,
                'template' => $key,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        $message->save();

        return $message;
    }

    /**
     * ارسال قالب ثبت‌شده و پرکردن نتیجه در پیام (بدون ذخیره).
     *
     * @throws SmsException
     */
    public function deliverTemplate(SmsTemplate $template, SmsMessage $message, array $values, array $secret = []): void
    {
        $parameters = [];
        $logged = [];

        foreach ($template->parameters as $variable => $parameterName) {
            // متغیر اختیاری بدون نام پارامتر در قالب SMS.ir نیست
            if ($parameterName === '' || $parameterName === null) {
                continue;
            }

            // SMS.ir مقدار بلندتر از ۲۵ نویسه را رد می‌کند و کل پیامک ارسال نمی‌شود
            $value = SmsText::fit((string) ($values[$variable] ?? ''));
            $parameters[$parameterName] = $value;
            $logged[$parameterName] = in_array($variable, $secret, true) ? '***' : $value;
        }

        $message->parameters = $logged;

        if (! $template->isConfigured() && ! $this->provider instanceof LogSmsProvider) {
            throw new SmsException("SMS template [{$template->key}] has no provider template ID.");
        }

        $result = $this->provider->sendTemplate(
            $message->mobile,
            (int) $template->provider_template_id,
            $parameters
        );

        $message->fill([
            'status' => SmsMessage::STATUS_SENT,
            'provider_message_id' => $result['message_id'],
            'cost' => $result['cost'],
            'response' => $result['response'] ?? null,
            'error' => null,
            'sent_at' => now(),
        ]);
    }

    /**
     * ثبت پیامک قالب‌دار در صف؛ null یعنی ثبت نشد (قالب غیرفعال یا پیام تکراری).
     *
     * @param  array<string, string|int>  $values
     * @param  array{contact_id?: int, user_id?: int}  $meta
     */
    public function queueTemplate(string $key, string $mobile, array $values, ?string $dedupeKey = null, array $meta = []): ?SmsMessage
    {
        $template = SmsTemplate::where('key', $key)->first();

        if (! $template?->enabled) {
            return null;
        }

        return $this->queue([
            'mobile' => $mobile,
            'template_key' => $key,
            'type' => SmsMessage::TYPE_TEMPLATE,
            'category' => $template->category,
            'variables' => array_map('strval', $values),
            'dedupe_key' => $dedupeKey,
        ] + $meta);
    }

    /**
     * ثبت متن آزاد در صف (ارسال گروهی یا دستی از خط اختصاصی).
     *
     * @param  array{contact_id?: int, user_id?: int, broadcast_id?: int}  $meta
     */
    public function queueText(string $mobile, string $body, string $category, ?string $dedupeKey = null, array $meta = []): ?SmsMessage
    {
        return $this->queue([
            'mobile' => $mobile,
            'type' => SmsMessage::TYPE_TEXT,
            'body' => $body,
            'category' => $category,
            'dedupe_key' => $dedupeKey,
        ] + $meta);
    }

    /**
     * پردازش صف پس از ارسال پاسخ به کاربر (یک بار در هر درخواست).
     * اگر اجرای PHP قطع شود، cron (sms:dispatch) پیام‌های باقی‌مانده را می‌فرستد.
     */
    public function dispatchSoon(): void
    {
        // نام یکسان: چند پیام در یک درخواست فقط یک بار پردازش صف را زمان‌بندی می‌کند
        defer(function () {
            try {
                app(SmsDispatcher::class)->run();
            } catch (\Throwable $e) {
                Log::error('Deferred SMS dispatch failed.', ['exception' => $e->getMessage()]);
            }
        }, 'sms-dispatch');
    }

    private function queue(array $attributes): ?SmsMessage
    {
        if ($attributes['dedupe_key'] && SmsMessage::where('dedupe_key', $attributes['dedupe_key'])->exists()) {
            return null;
        }

        try {
            $message = SmsMessage::create($attributes + [
                'provider' => $this->provider->name(),
                'status' => SmsMessage::STATUS_QUEUED,
                'available_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        $this->dispatchSoon();

        return $message;
    }
}

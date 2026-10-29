<?php

namespace App\Services\Sms;

interface SmsProvider
{
    public function name(): string;

    /**
     * ارسال پیامک قالب‌دار (Verify).
     *
     * @param  array<string, string>  $parameters  نام پارامتر قالب => مقدار
     * @return array{message_id: ?string, cost: ?float}
     *
     * @throws SmsException
     */
    public function sendTemplate(string $mobile, int $templateId, array $parameters): array;
}

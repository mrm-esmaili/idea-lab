<?php

namespace App\Services\Sms;

interface SmsProvider
{
    public function name(): string;

    /**
     * ارسال پیامک قالب‌دار (Verify).
     *
     * @param  array<string, string>  $parameters  نام پارامتر قالب => مقدار
     * @return array{message_id: ?string, cost: ?float, response: ?array}
     *
     * @throws SmsException
     */
    public function sendTemplate(string $mobile, int $templateId, array $parameters): array;

    /**
     * ارسال یک متن به چند گیرنده از خط اختصاصی (ارسال گروهی).
     *
     * @param  list<string>  $mobiles
     * @return array{message_ids: list<?string>, cost: ?float, response: ?array}
     *
     * @throws SmsException
     */
    public function sendText(array $mobiles, string $text): array;
}

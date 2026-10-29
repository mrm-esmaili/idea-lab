<?php

namespace App\Services\Sms;

use RuntimeException;
use Throwable;

/**
 * retryable: خطای گذرا (عدم دسترسی، خطای سرور ارائه‌دهنده) که تلاش دوباره
 * معنا دارد؛ رد قطعی (اعتبار ناکافی، قالب نامعتبر، شماره نامعتبر) قابل تکرار نیست.
 */
class SmsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $providerCode = null,
        ?Throwable $previous = null,
        public readonly bool $retryable = false,
        public readonly ?array $response = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

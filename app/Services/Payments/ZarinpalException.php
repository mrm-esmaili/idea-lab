<?php

namespace App\Services\Payments;

use RuntimeException;
use Throwable;

/**
 * خطای درگاه زرین‌پال.
 *
 * اگر gatewayCode مقدار داشته باشد، درگاه پاسخ قطعی داده است (مثلاً
 * -51 پرداخت ناموفق یا -50 مغایرت مبلغ). اگر null باشد، پاسخ قطعی
 * دریافت نشده (قطعی شبکه، خطای 5xx یا پاسخ نامعتبر) و نتیجه نامعلوم است.
 */
class ZarinpalException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $gatewayCode = null,
        public readonly array $response = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function isDefinitive(): bool
    {
        return $this->gatewayCode !== null;
    }
}

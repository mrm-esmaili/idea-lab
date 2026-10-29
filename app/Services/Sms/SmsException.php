<?php

namespace App\Services\Sms;

use RuntimeException;
use Throwable;

class SmsException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?int $providerCode = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}

<?php

namespace App\Rules;

use App\Support\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class IranianMobile implements ValidationRule
{
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        if (
            ! is_string($value) ||
            PhoneNumber::normalizeMobile($value) === null
        ) {
            $fail('شماره موبایل معتبر نیست. نمونه صحیح: 09123456789');
        }
    }
}

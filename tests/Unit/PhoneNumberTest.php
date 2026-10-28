<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public static function validMobiles(): array
    {
        return [
            ['09123456789'],
            ['۰۹۱۲۳۴۵۶۷۸۹'],
            ['٠٩١٢٣٤٥٦٧٨٩'],
            ['0912 345 6789'],
            ['0912-345-6789'],
            ['+989123456789'],
            ['+98 912 345 6789'],
            ['00989123456789'],
            ['989123456789'],
            ['9123456789'],
        ];
    }

    #[DataProvider('validMobiles')]
    public function test_valid_mobiles_are_normalized(string $input): void
    {
        $this->assertSame(
            '09123456789',
            PhoneNumber::normalizeMobile($input)
        );
    }

    public static function invalidMobiles(): array
    {
        return [
            [''],
            ['abc'],
            ['02112345678'],
            ['0912345678'],
            ['091234567890'],
            ['+19123456789'],
        ];
    }

    #[DataProvider('invalidMobiles')]
    public function test_invalid_mobiles_return_null(string $input): void
    {
        $this->assertNull(PhoneNumber::normalizeMobile($input));
    }
}

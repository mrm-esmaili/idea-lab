<?php

namespace App\Support;

/**
 * یک راه تماس با پشتیبانی (مرحله ۱۱۴). لینک از نوع و مقدار ساخته می‌شود و
 * هیچ نشانی دلخواهی از مدیر پذیرفته نمی‌شود.
 */
final class ContactChannel
{
    public const TYPES = [
        'bale' => 'بله',
        'whatsapp' => 'واتساپ',
        'telegram' => 'تلگرام',
        'eitaa' => 'ایتا',
        'phone' => 'تلفن',
        'email' => 'ایمیل',
    ];

    /** پیام‌رسان‌هایی که با نام کاربری لینک مستقیم دارند */
    private const USERNAME_HOSTS = [
        'bale' => 'https://ble.ir/',
        'telegram' => 'https://t.me/',
        'eitaa' => 'https://eitaa.com/',
    ];

    private const USERNAME = '/^@?([A-Za-z][A-Za-z0-9_]{3,31})$/';

    public function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly ?string $label = null,
    ) {
    }

    public static function fromArray(array $data): ?self
    {
        $type = (string) ($data['type'] ?? '');
        $value = trim((string) ($data['value'] ?? ''));

        if (! isset(self::TYPES[$type]) || $value === '' || self::validationError($type, $value) !== null) {
            return null;
        }

        $label = trim((string) ($data['label'] ?? ''));

        return new self($type, $value, $label !== '' ? $label : null);
    }

    /**
     * @return string|null پیام خطا برای فرم تنظیمات
     */
    public static function validationError(string $type, string $value): ?string
    {
        return match ($type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'نشانی ایمیل معتبر نیست.',
            'phone', 'whatsapp' => self::digits($value) !== null ? null : 'شماره تلفن معتبر نیست (مثلاً 09121234567 یا 02112345678).',
            default => preg_match(self::USERNAME, $value) || self::digits($value) !== null ? null : 'نام کاربری (حروف انگلیسی، عدد و زیرخط) یا شماره موبایل وارد کنید.',
        };
    }

    public function name(): string
    {
        return $this->label ?? self::TYPES[$this->type];
    }

    public function display(): string
    {
        if (preg_match(self::USERNAME, $this->value, $match) && $this->type !== 'email') {
            return '@' . $match[1];
        }

        return self::digits($this->value) ?? $this->value;
    }

    public function url(): ?string
    {
        $digits = self::digits($this->value);

        if (isset(self::USERNAME_HOSTS[$this->type]) && preg_match(self::USERNAME, $this->value, $match)) {
            return self::USERNAME_HOSTS[$this->type] . $match[1];
        }

        return match ($this->type) {
            'whatsapp' => $digits ? 'https://wa.me/' . self::international($digits) : null,
            'telegram' => $digits ? 'https://t.me/+' . self::international($digits) : null,
            'phone' => $digits ? 'tel:' . $digits : null,
            'email' => 'mailto:' . $this->value,
            // بله و ایتا با شماره لینک مستقیم ندارند؛ شماره نمایش داده می‌شود
            default => null,
        };
    }

    public function toArray(): array
    {
        return ['type' => $this->type, 'value' => $this->value, 'label' => $this->label];
    }

    private static function digits(string $value): ?string
    {
        $mobile = PhoneNumber::normalizeMobile($value);
        if ($mobile !== null) {
            return $mobile;
        }

        $digits = preg_replace('/\D+/', '', strtr($value, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']));

        // تلفن ثابت ایران با کد شهر
        return preg_match('/^0[1-8]\d{9}$/', $digits) ? $digits : null;
    }

    private static function international(string $digits): string
    {
        return '98' . substr($digits, 1);
    }
}

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

    /** لینک کامل فقط روی دامنه رسمی همان پیام‌رسان پذیرفته می‌شود */
    private const LINK_HOSTS = [
        'whatsapp' => ['wa.me', 'api.whatsapp.com', 'chat.whatsapp.com', 'whatsapp.com', 'www.whatsapp.com'],
        'telegram' => ['t.me', 'telegram.me'],
        'bale' => ['ble.ir'],
        'eitaa' => ['eitaa.com', 'www.eitaa.com'],
    ];

    private const USERNAME = '/^@?([A-Za-z][A-Za-z0-9_.]{2,31})$/';

    public function __construct(
        public readonly string $type,
        public readonly string $value,
        public readonly ?string $label = null,
        // متن نمایشی به‌جای مقدار (مثلاً شناسه، وقتی مقدار لینک است)
        public readonly ?string $displayText = null,
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
        $display = mb_substr(trim((string) ($data['display'] ?? '')), 0, 60);

        return new self($type, $value, $label !== '' ? $label : null, $display !== '' ? $display : null);
    }

    /**
     * @return string|null پیام خطا برای فرم تنظیمات
     */
    public static function validationError(string $type, string $value): ?string
    {
        $phone = self::international($value) !== null;
        $username = (bool) preg_match(self::USERNAME, $value);
        $link = self::link($type, $value) !== null;

        return match ($type) {
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) ? null : 'نشانی ایمیل معتبر نیست.',
            'phone' => $phone ? null : 'شماره تلفن معتبر نیست (مثلاً 09121234567، 02112345678 یا +971501234567).',
            'whatsapp' => $phone || $link || $username ? null : 'شماره موبایل، لینک واتساپ (wa.me/... یا chat.whatsapp.com/...) یا شناسه را وارد کنید.',
            default => $phone || $link || $username ? null : 'نام کاربری (حروف انگلیسی، عدد و زیرخط)، لینک کامل یا شماره موبایل وارد کنید.',
        };
    }

    public function name(): string
    {
        return $this->label ?? self::TYPES[$this->type];
    }

    /**
     * متن کنار نام پیام‌رسان؛ رشته خالی یعنی فقط نام نمایش داده شود.
     */
    public function display(): string
    {
        if ($this->displayText !== null) {
            return $this->displayText;
        }

        // لینک خودش نمایش داده نمی‌شود؛ فقط شناسه یا شماره‌ای که از آن پیداست
        if ($link = self::link($this->type, $this->value)) {
            $path = trim((string) parse_url($link, PHP_URL_PATH), '/');

            if (preg_match('/^\d{8,15}$/', $path)) {
                return str_starts_with($path, '98') ? '0' . substr($path, 2) : '+' . $path;
            }

            return in_array($this->type, ['telegram', 'bale', 'eitaa'], true) && preg_match(self::USERNAME, $path, $match) ? '@' . $match[1] : '';
        }

        if ($this->type !== 'email' && preg_match(self::USERNAME, $this->value, $match)) {
            return '@' . $match[1];
        }

        if ($this->type !== 'email' && ($international = self::international($this->value))) {
            // شماره ایران به شکل آشنای 09...، شماره خارجی با +
            return str_starts_with($international, '98') ? '0' . substr($international, 2) : '+' . $international;
        }

        return $this->value;
    }

    public function url(): ?string
    {
        if ($link = self::link($this->type, $this->value)) {
            return $link;
        }

        if (isset(self::USERNAME_HOSTS[$this->type]) && preg_match(self::USERNAME, $this->value, $match)) {
            return self::USERNAME_HOSTS[$this->type] . $match[1];
        }

        $international = self::international($this->value);

        return match ($this->type) {
            'whatsapp' => $international ? 'https://wa.me/' . $international : null,
            'telegram' => $international ? 'https://t.me/+' . $international : null,
            'phone' => $international ? 'tel:+' . $international : null,
            'email' => 'mailto:' . $this->value,
            // بله و ایتا با شماره، و واتساپ با شناسه، لینک مستقیم ندارند؛ متن نمایش داده می‌شود
            default => null,
        };
    }

    public function toArray(): array
    {
        return ['type' => $this->type, 'value' => $this->value, 'label' => $this->label, 'display' => $this->displayText];
    }

    /**
     * شماره بین‌المللی بدون + (برای wa.me و tel:)، یا null.
     * ایران: موبایل 09... و ثابت 0XX...؛ خارج: با + یا 00 و ۸ تا ۱۵ رقم.
     */
    private static function international(string $value): ?string
    {
        $value = strtr(trim($value), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);

        if (! preg_match('/^\+?[\d\s()\-]+$/', $value)) {
            return null;
        }

        if ($mobile = PhoneNumber::normalizeMobile($value)) {
            return '98' . substr($mobile, 1);
        }

        $digits = preg_replace('/\D+/', '', $value);

        if (preg_match('/^0[1-8]\d{9}$/', $digits)) {
            return '98' . substr($digits, 1);
        }

        if (str_starts_with($value, '+') || str_starts_with($digits, '00')) {
            $digits = preg_replace('/^00/', '', $digits);

            return preg_match('/^[1-9]\d{7,14}$/', $digits) ? $digits : null;
        }

        return null;
    }

    /**
     * لینک کامل روی دامنه رسمی همان پیام‌رسان ← نشانی https استاندارد، یا null.
     */
    private static function link(string $type, string $value): ?string
    {
        if (! isset(self::LINK_HOSTS[$type]) || preg_match('/[\s"\'<>\\\\]/', $value)) {
            return null;
        }

        $url = preg_match('#^https?://#i', $value) ? $value : 'https://' . $value;
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';

        if (! in_array($host, self::LINK_HOSTS[$type], true) || isset($parts['user']) || isset($parts['port']) || trim($path, '/') === '') {
            return null;
        }

        return 'https://' . $host . $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
    }
}

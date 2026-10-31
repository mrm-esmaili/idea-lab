<?php

namespace App\Services;

use App\Models\SiteSetting;
use App\Models\User;
use App\Support\ContactChannel;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * تنظیمات عمومی سایت که مدیر از پنل تغییر می‌دهد (مراحل ۱۱۱ تا ۱۱۴).
 */
class SiteSettings
{
    public const DEFAULTS = [
        'company' => ['name' => null, 'website' => null, 'logo' => null, 'intro' => null],
        'trust_seal' => ['enamad_id' => null, 'enamad_code' => null],
        'support' => ['help_text' => null, 'channels' => []],
    ];

    private const CACHE_KEY = 'site_settings';

    private ?array $loaded = null;

    public function all(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        try {
            $stored = Cache::rememberForever(self::CACHE_KEY, fn () => SiteSetting::pluck('value', 'key')->all());
        } catch (Throwable) {
            // پیش از اجرای migration (مثلاً میان Extract دستی و migrate) صفحات عمومی نباید از کار بیفتند
            $stored = [];
        }

        $settings = [];
        foreach (self::DEFAULTS as $group => $defaults) {
            $settings[$group] = array_merge($defaults, array_intersect_key((array) ($stored[$group] ?? []), $defaults));
        }

        return $this->loaded = $settings;
    }

    public function get(string $group): array
    {
        return $this->all()[$group];
    }

    public function save(string $group, array $value, ?User $user): void
    {
        SiteSetting::updateOrCreate(
            ['key' => $group],
            ['value' => array_intersect_key($value, self::DEFAULTS[$group]), 'updated_by' => $user?->id],
        );

        Cache::forget(self::CACHE_KEY);
        $this->loaded = null;
    }

    public function companyName(): ?string
    {
        return $this->get('company')['name'];
    }

    public function logoUrl(): ?string
    {
        $logo = $this->get('company')['logo'];

        return is_string($logo) && $logo !== '' ? asset('uploads/' . $logo) : null;
    }

    /**
     * @return list<ContactChannel>
     */
    public function supportChannels(): array
    {
        return array_values(array_filter(array_map(
            fn ($channel) => is_array($channel) ? ContactChannel::fromArray($channel) : null,
            (array) $this->get('support')['channels']
        )));
    }

    /**
     * لینک و تصویر استاندارد نماد اعتماد الکترونیکی؛ HTML دلخواه ذخیره نمی‌شود.
     *
     * @return array{url: string, image: string, code: string}|null
     */
    public function enamad(): ?array
    {
        ['enamad_id' => $id, 'enamad_code' => $code] = $this->get('trust_seal');

        if (! is_string($id) || ! is_string($code) || ! preg_match('/^\d{3,12}$/', $id) || ! preg_match('/^[A-Za-z0-9]{4,64}$/', $code)) {
            return null;
        }

        $query = http_build_query(['id' => $id, 'Code' => $code]);

        return [
            'url' => 'https://trustseal.enamad.ir/?' . $query,
            'image' => 'https://trustseal.enamad.ir/logo.aspx?' . $query,
            'code' => $code,
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class LandingPage extends Model
{
    /**
     * ساختار ستون content و مقدار پیش‌فرض هر کلید.
     */
    public const CONTENT_DEFAULTS = [
        'body_title' => null,
        'body_text' => null,
        // هر ویژگی: ['title' => ..., 'description' => ...]؛ رشته ساده (نسخه ۱.۰) = عنوان بدون توضیح
        'features' => [],
        'faq' => [],
        'hero_image' => null,
        'show_lead_form' => true,
        'show_preorder_form' => true,

        // نسخه ۱.۱، بخش S
        'demo_url' => null,
        'demo_label' => null,
        'demo_new_tab' => true,
        'video_aparat' => null,
        'video_file' => null,
        'preorder_title' => null,
        'preorder_intro' => null,
        'preorder_badge' => null,
        'preorder_original_price_toman' => null,
        'preorder_includes' => [],
        'preorder_guarantee' => null,
        'preorder_button' => null,
        'support_text' => null,
        // خالی = راه‌های تماس تنظیمات سایت
        'support_channels' => [],
        'show_trust' => true,
    ];

    public const APARAT_HASH = '/^[A-Za-z0-9]{3,24}$/';

    protected $fillable = [
        'idea_id',
        'name',
        'slug',
        'title',
        'description',
        'cta_text',
        'content',
        'is_active',
    ];

    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
    ];

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function events()
    {
        return $this->hasMany(LandingPageEvent::class);
    }
    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function preorders()
    {
        return $this->hasMany(\App\Models\Preorder::class);
    }

    /**
     * صفحه فعال که ایده آن بایگانی نشده است.
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('is_active', true)
            ->whereHas('idea', fn ($idea) => $idea->whereNull('archived_at'));
    }

    /**
     * محتوای صفحه با مقادیر پیش‌فرض برای کلیدهای ثبت‌نشده.
     */
    public function contentData(): array
    {
        $content = array_merge(
            self::CONTENT_DEFAULTS,
            array_intersect_key(
                (array) $this->content,
                self::CONTENT_DEFAULTS
            )
        );

        $content['features'] = array_values(array_filter(array_map(
            fn ($feature) => match (true) {
                is_string($feature) => ['title' => $feature, 'description' => null],
                is_array($feature) && is_string($feature['title'] ?? null) => [
                    'title' => $feature['title'],
                    'description' => is_string($feature['description'] ?? null) && $feature['description'] !== '' ? $feature['description'] : null,
                ],
                default => null,
            },
            (array) $content['features']
        )));

        $content['faq'] = array_values(array_filter(
            (array) $content['faq'],
            fn ($item) => is_array($item)
                && is_string($item['q'] ?? null)
                && is_string($item['a'] ?? null)
        ));

        $content['preorder_includes'] = array_values(array_filter((array) $content['preorder_includes'], 'is_string'));
        $content['support_channels'] = array_values(array_filter((array) $content['support_channels'], 'is_array'));
        $content['preorder_original_price_toman'] = is_numeric($content['preorder_original_price_toman']) ? (int) $content['preorder_original_price_toman'] : null;
        $content['video_aparat'] = is_string($content['video_aparat']) && preg_match(self::APARAT_HASH, $content['video_aparat']) ? $content['video_aparat'] : null;
        $content['demo_new_tab'] = (bool) $content['demo_new_tab'];
        $content['show_trust'] = (bool) $content['show_trust'];

        $content['show_lead_form'] = (bool) $content['show_lead_form'];
        $content['show_preorder_form'] = (bool) $content['show_preorder_form'];

        return $content;
    }

    public function videoUrl(): ?string
    {
        $path = $this->contentData()['video_file'];

        return is_string($path) && $path !== '' ? asset('uploads/' . $path) : null;
    }

    public function aparatEmbedUrl(): ?string
    {
        $hash = $this->contentData()['video_aparat'];

        return $hash ? 'https://www.aparat.com/video/video/embed/videohash/' . $hash . '/vt/frame' : null;
    }

    /**
     * شناسه ویدیو از نشانی صفحه یا embed آپارات؛ فقط دامنه aparat.com پذیرفته می‌شود.
     */
    public static function aparatHash(string $url): ?string
    {
        $url = trim($url);

        if (preg_match(self::APARAT_HASH, $url)) {
            return $url;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (! in_array($host, ['aparat.com', 'www.aparat.com'], true)) {
            return null;
        }

        if (preg_match('#^/v/([A-Za-z0-9]{3,24})(?:/|$)#', $path, $match) || preg_match('#/videohash/([A-Za-z0-9]{3,24})(?:/|$)#', $path, $match)) {
            return $match[1];
        }

        return null;
    }

    /**
     * راه‌های تماس این صفحه، یا راه‌های تماس تنظیمات سایت.
     *
     * @return list<\App\Support\ContactChannel>
     */
    public function supportChannels(): array
    {
        $own = array_values(array_filter(array_map(
            fn ($channel) => \App\Support\ContactChannel::fromArray($channel),
            $this->contentData()['support_channels']
        )));

        return $own ?: app(\App\Services\SiteSettings::class)->supportChannels();
    }

    public function heroImageUrl(): ?string
    {
        $path = $this->contentData()['hero_image'];

        return is_string($path) && $path !== ''
            ? asset('uploads/' . $path)
            : null;
    }
}

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
        'features' => [],
        'faq' => [],
        'hero_image' => null,
        'show_lead_form' => true,
        'show_preorder_form' => true,
    ];

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

        $content['features'] = array_values(array_filter(
            (array) $content['features'],
            'is_string'
        ));

        $content['faq'] = array_values(array_filter(
            (array) $content['faq'],
            fn ($item) => is_array($item)
                && is_string($item['q'] ?? null)
                && is_string($item['a'] ?? null)
        ));

        $content['show_lead_form'] = (bool) $content['show_lead_form'];
        $content['show_preorder_form'] = (bool) $content['show_preorder_form'];

        return $content;
    }

    public function heroImageUrl(): ?string
    {
        $path = $this->contentData()['hero_image'];

        return is_string($path) && $path !== ''
            ? asset('uploads/' . $path)
            : null;
    }
}

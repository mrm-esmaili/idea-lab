<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * قاعده انتساب: رویداد، سرنخ یا پیش‌خرید متعلق به کمپین است اگر روی صفحه
 * فرود همان کمپین ثبت شده و مقدار utm_campaign آن برابر کد کمپین (slug)
 * باشد. مقدار utm_campaign هنگام اقدام ثبت می‌شود: آخرین مقدار دیده‌شده
 * از همان بازدیدکننده روی همان صفحه، و در نبود آن، مقدار اولین ورود او.
 * انتساب به تاریخ اجرای کمپین وابسته نیست.
 */
class Campaign extends Model
{
    public const STATUSES = ['planned', 'running', 'paused', 'ended'];

    protected $fillable = [
        'landing_page_id',
        'name',
        'slug',
        'source',
        'medium',
        'status',
        'starts_on',
        'ends_on',
        'budget_rial',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'budget_rial' => 'integer',
        ];
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function leads(): Builder
    {
        return Lead::query()
            ->where('landing_page_id', $this->landing_page_id)
            ->where('campaign', $this->slug);
    }

    public function preorders(): Builder
    {
        return Preorder::query()
            ->where('landing_page_id', $this->landing_page_id)
            ->where('campaign', $this->slug);
    }

    /**
     * لینک رهگیری برای استفاده در تبلیغ.
     */
    public function trackingUrl(): string
    {
        $query = array_filter([
            'utm_source' => $this->source,
            'utm_medium' => $this->medium,
            'utm_campaign' => $this->slug,
        ]);

        return route('landing-pages.show', $this->landingPage->slug)
            . '?' . http_build_query($query);
    }
}

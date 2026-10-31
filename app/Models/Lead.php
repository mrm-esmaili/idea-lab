<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    public const INTENT_INTEREST = 'interest';

    /** «وقتی آماده شد خبرم کن» (مرحله ۱۳۰) */
    public const INTENT_NOTIFY = 'notify_when_ready';

    public const INTENTS = [
        self::INTENT_INTEREST => 'ثبت علاقه',
        self::INTENT_NOTIFY => 'خبرم کن',
    ];

    protected $fillable = [
        'landing_page_id',
        'visitor_id',
        'contact_id',
        'name',
        'phone',
        'email',
        'status',
        'intent',
        'source',
        'medium',
        'campaign',
        'content',
        'term',
        'notes',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }

    public function intentLabel(): string
    {
        return self::INTENTS[$this->intent] ?? self::INTENTS[self::INTENT_INTEREST];
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}

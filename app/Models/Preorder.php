<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Preorder extends Model
{
    protected $fillable = [
        'landing_page_id',
        'visitor_id',
        'contact_id',
        'lead_id',
        'name',
        'phone',
        'amount',
        'amount_rial',
        'refund_policy_id',
        'terms_accepted_at',
        'status',
        'payment_status',
        'source',
        'medium',
        'campaign',
        'consented_at',
        'public_token',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_rial' => 'integer',
            'terms_accepted_at' => 'datetime',
            'consented_at' => 'datetime',
        ];
    }

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_PARTIALLY_REFUNDED = 'partially_refunded';
    public const PAYMENT_REFUNDED = 'refunded';

    /** سفارش پرداخت شده است (حتی اگر بعداً بازپرداخت شده باشد) */
    public const PAID_STATUSES = [self::PAYMENT_PAID, self::PAYMENT_PARTIALLY_REFUNDED, self::PAYMENT_REFUNDED];

    /**
     * پرداخت تأییدشده دارد؛ سفارش بازپرداخت‌شده هم دوباره قابل پرداخت نیست.
     */
    public function isPaid(): bool
    {
        return in_array($this->payment_status, self::PAID_STATUSES, true);
    }

    public function refundRequests(): HasMany
    {
        return $this->hasMany(RefundRequest::class);
    }

    public function refundPolicy(): BelongsTo
    {
        return $this->belongsTo(RefundPolicy::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}

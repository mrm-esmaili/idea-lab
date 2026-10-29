<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RefundRequest extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_COMPLETED = 'completed';

    /** وضعیت‌هایی که درخواست هنوز باز است */
    public const OPEN = [self::STATUS_PENDING, self::STATUS_APPROVED];

    protected $fillable = [
        'preorder_id',
        'payment_id',
        'contact_id',
        'refund_policy_id',
        'requested_by_type',
        'requested_by_user_id',
        'requested_amount_rial',
        'reason',
        'status',
        'approved_amount_rial',
        'decision_note',
        'decided_by',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount_rial' => 'integer',
            'approved_amount_rial' => 'integer',
            'decided_at' => 'datetime',
        ];
    }

    public function preorder(): BelongsTo
    {
        return $this->belongsTo(Preorder::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function refundPolicy(): BelongsTo
    {
        return $this->belongsTo(RefundPolicy::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }

    public function executedAmount(): int
    {
        return (int) $this->refunds()->sum('amount_rial');
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }
}

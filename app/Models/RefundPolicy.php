<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * نسخه تغییرناپذیر شرایط بازپرداخت؛ برای تغییر، نسخه جدید ساخته می‌شود.
 */
class RefundPolicy extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'idea_id',
        'version',
        'is_refundable',
        'refund_window_days',
        'eligibility',
        'terms',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_refundable' => 'boolean',
            'refund_window_days' => 'integer',
            'version' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Refund policy versions are immutable.');
        });

        static::deleting(function () {
            throw new LogicException('Refund policy versions are immutable.');
        });
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function preorders(): HasMany
    {
        return $this->hasMany(Preorder::class);
    }

    public function summary(): string
    {
        if (! $this->is_refundable) {
            return 'غیرقابل بازپرداخت';
        }

        return $this->refund_window_days
            ? "قابل بازپرداخت تا {$this->refund_window_days} روز پس از پرداخت"
            : 'قابل بازپرداخت';
    }
}

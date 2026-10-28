<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'preorder_id',
        'gateway',
        'authority',
        'amount_rial',
        'status',
        'ref_id',
        'card_pan',
        'failure_reason',
        'gateway_response',
        'verified_at',
    ];

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected function casts(): array
    {
        return [
            'amount_rial' => 'integer',
            'verified_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    public function preorder(): BelongsTo
    {
        return $this->belongsTo(Preorder::class);
    }
}

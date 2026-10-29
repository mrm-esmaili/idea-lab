<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    public const UPDATED_AT = null;

    public const METHODS = [
        'bank_transfer' => 'انتقال بانکی (پایا/ساتنا)',
        'card_to_card' => 'کارت به کارت',
        'gateway' => 'بازگشت از درگاه',
        'cash' => 'نقدی',
        'other' => 'سایر',
    ];

    protected $fillable = [
        'refund_request_id',
        'payment_id',
        'amount_rial',
        'method',
        'reference',
        'refunded_on',
        'note',
        'executed_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_rial' => 'integer',
            'refunded_on' => 'date',
        ];
    }

    public function refundRequest(): BelongsTo
    {
        return $this->belongsTo(RefundRequest::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }
}

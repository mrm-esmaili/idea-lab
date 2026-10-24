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
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_rial' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function preorder(): BelongsTo
    {
        return $this->belongsTo(Preorder::class);
    }
}

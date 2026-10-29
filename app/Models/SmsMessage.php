<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsMessage extends Model
{
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'mobile',
        'template_key',
        'category',
        'provider',
        'status',
        'provider_message_id',
        'cost',
        'parameters',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'cost' => 'decimal:2',
        ];
    }
}

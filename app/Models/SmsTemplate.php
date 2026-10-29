<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    protected $fillable = [
        'key',
        'name',
        'category',
        'provider_template_id',
        'parameters',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'provider_template_id' => 'integer',
            'parameters' => 'array',
        ];
    }

    public function isConfigured(): bool
    {
        return $this->provider_template_id !== null && $this->provider_template_id > 0;
    }
}

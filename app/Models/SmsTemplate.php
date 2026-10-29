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
        'body',
        'enabled',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'provider_template_id' => 'integer',
            'parameters' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function isConfigured(): bool
    {
        return $this->provider_template_id !== null && $this->provider_template_id > 0;
    }

    /**
     * پیش‌نمایش متن با مقادیر نمونه یا واقعی (متغیر داخلی => مقدار).
     */
    public function render(array $values): string
    {
        $text = (string) $this->body;

        foreach ($this->parameters as $variable => $name) {
            $text = str_replace('#' . $name . '#', (string) ($values[$variable] ?? ''), $text);
        }

        return $text;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    protected $fillable = [
        'uuid',
        'first_source',
        'first_medium',
        'first_campaign',
        'first_content',
        'first_term',
        'first_seen_at',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(LandingPageEvent::class);
    }
}

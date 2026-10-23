<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    protected $fillable = [
        'landing_page_id',
        'visitor_id',
        'name',
        'phone',
        'email',
        'status',
        'source',
        'medium',
        'campaign',
        'content',
        'term',
        'notes',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
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
}

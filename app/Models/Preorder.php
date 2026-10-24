<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Preorder extends Model
{
    protected $fillable = [
        'landing_page_id',
        'visitor_id',
        'lead_id',
        'name',
        'phone',
        'amount',
        'status',
        'source',
        'medium',
        'campaign',
        'consented_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
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

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }
}

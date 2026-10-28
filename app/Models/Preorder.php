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
        'amount_rial',
        'refund_policy_id',
        'terms_accepted_at',
        'status',
        'source',
        'medium',
        'campaign',
        'consented_at',
        'public_token',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'amount_rial' => 'integer',
            'terms_accepted_at' => 'datetime',
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

    public function refundPolicy(): BelongsTo
    {
        return $this->belongsTo(RefundPolicy::class);
    }

    public function payments()
    {
        return $this->hasMany(\App\Models\Payment::class);
    }
}

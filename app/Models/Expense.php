<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    public const CATEGORIES = [
        'ads' => 'تبلیغات',
        'development' => 'توسعه',
        'design' => 'طراحی',
        'operations' => 'عملیات و پشتیبانی',
        'other' => 'سایر',
    ];

    public const COST_TYPES = [
        'fixed' => 'ثابت',
        'variable' => 'متغیر',
    ];

    protected $fillable = [
        'idea_id',
        'campaign_id',
        'category',
        'cost_type',
        'amount_rial',
        'incurred_on',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount_rial' => 'integer',
            'incurred_on' => 'date',
        ];
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

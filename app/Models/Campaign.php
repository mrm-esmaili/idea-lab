<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $fillable = [
        'landing_page_id',
        'name',
        'slug',
        'source',
        'medium',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function landingPage()
    {
        return $this->belongsTo(LandingPage::class);
    }
}

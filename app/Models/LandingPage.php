<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingPage extends Model
{
    protected $fillable = [
        'idea_id',
        'name',
        'slug',
        'title',
        'description',
        'cta_text',
        'content',
        'is_active',
    ];

    protected $casts = [
        'content' => 'array',
        'is_active' => 'boolean',
    ];

    public function idea()
    {
        return $this->belongsTo(Idea::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function events()
    {
        return $this->hasMany(LandingPageEvent::class);
    }
    public function leads()
    {
        return $this->hasMany(Lead::class);
    }

    public function preorders()
    {
        return $this->hasMany(\App\Models\Preorder::class);
    }

}

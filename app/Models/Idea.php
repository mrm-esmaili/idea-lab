<?php

namespace App\Models;

use App\Enums\IdeaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Idea extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }

    public function landingPages()
    {
        return $this->hasMany(LandingPage::class);
    }

    public function leads(): HasManyThrough
    {
        return $this->hasManyThrough(Lead::class, LandingPage::class);
    }

    public function preorders(): HasManyThrough
    {
        return $this->hasManyThrough(Preorder::class, LandingPage::class);
    }

    public function statusChanges(): HasMany
    {
        return $this->hasMany(IdeaStatusChange::class)
            ->latest('created_at')
            ->latest('id');
    }

    public function statusEnum(): IdeaStatus
    {
        return IdeaStatus::from($this->status);
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function scopeActive(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    public function scopeArchived(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }
}

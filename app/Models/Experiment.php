<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * وضعیت آزمایش ذخیره نمی‌شود و از تاریخ‌ها، لغو و تصمیم‌ها به دست می‌آید:
 * planned (قبل از شروع) ← running ← ended (پایان بازه، منتظر تصمیم) ← decided.
 */
class Experiment extends Model
{
    public const STATUSES = ['planned', 'running', 'ended', 'decided', 'cancelled'];

    protected $fillable = [
        'idea_id',
        'title',
        'hypothesis',
        'goal',
        'starts_on',
        'ends_on',
        'budget_rial',
        'min_sample',
        'criteria',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'budget_rial' => 'integer',
            'min_sample' => 'integer',
            'criteria' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(Campaign::class, 'experiment_campaign');
    }

    public function decisions(): HasMany
    {
        return $this->hasMany(ExperimentDecision::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function status(): string
    {
        $today = now()->timezone(config('app.display_timezone'))->toDateString();

        return match (true) {
            $this->cancelled_at !== null => 'cancelled',
            ($this->relationLoaded('decisions') ? $this->decisions->isNotEmpty() : $this->decisions()->exists()) => 'decided',
            $today < $this->starts_on->toDateString() => 'planned',
            $today <= $this->ends_on->toDateString() => 'running',
            default => 'ended',
        };
    }
}

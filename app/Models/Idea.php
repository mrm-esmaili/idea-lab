<?php

namespace App\Models;

use App\Enums\IdeaStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Idea extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'budget_rial',
        'test_starts_on',
        'test_ends_on',
        'lead_goal',
        'paid_preorder_goal',
        'preorder_amount_rial',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'budget_rial' => 'integer',
            'test_starts_on' => 'date',
            'test_ends_on' => 'date',
            'lead_goal' => 'integer',
            'paid_preorder_goal' => 'integer',
            'preorder_amount_rial' => 'integer',
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

    public function experiments(): HasMany
    {
        return $this->hasMany(Experiment::class);
    }

    public function refundPolicies(): HasMany
    {
        return $this->hasMany(RefundPolicy::class)->orderByDesc('version');
    }

    public function currentRefundPolicy(): HasOne
    {
        return $this->hasOne(RefundPolicy::class)->latestOfMany('version');
    }

    /**
     * دلایل باز نبودن پیش‌خرید؛ آرایه خالی یعنی پیش‌خرید باز است.
     *
     * @return list<string>
     */
    public function preorderBlockers(): array
    {
        $blockers = [];

        if (! $this->preorder_amount_rial) {
            $blockers[] = 'مبلغ پیش‌پرداخت تعیین نشده است.';
        }

        if (! $this->currentRefundPolicy) {
            $blockers[] = 'شرایط بازپرداخت تعیین نشده است.';
        }

        return $blockers;
    }

    public function acceptsPreorders(): bool
    {
        return $this->preorderBlockers() === [];
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

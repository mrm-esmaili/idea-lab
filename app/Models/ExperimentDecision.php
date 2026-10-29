<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExperimentDecision extends Model
{
    public const DECISIONS = [
        'continue' => 'ادامه',
        'modify' => 'اصلاح',
        'stop' => 'توقف',
        'execute' => 'اجرا',
    ];

    public const OUTCOMES = [
        'better' => 'بهتر از انتظار',
        'as_expected' => 'مطابق انتظار',
        'worse' => 'بدتر از انتظار',
    ];

    protected $fillable = [
        'experiment_id',
        'decision',
        'reason',
        'snapshot',
        'decided_by',
        'review_on',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'review_snapshot' => 'array',
            'review_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(Experiment::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isReviewDue(): bool
    {
        return $this->review_on !== null
            && $this->reviewed_at === null
            && $this->review_on->toDateString() <= now()->timezone(config('app.display_timezone'))->toDateString();
    }
}

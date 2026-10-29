<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IdeaStatusChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'idea_id',
        'user_id',
        'from_status',
        'to_status',
        'note',
    ];

    public function idea(): BelongsTo
    {
        return $this->belongsTo(Idea::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

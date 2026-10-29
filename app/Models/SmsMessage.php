<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * سابقه و صف پیامک.
 *
 * چرخه: queued → sending → sent | failed؛ skipped (مثلاً لغو رضایت پیش از
 * ارسال) و cancelled (لغو ارسال گروهی). پیام «sending» یعنی درخواست به
 * ارائه‌دهنده رفته و نتیجه‌اش هنوز ثبت نشده؛ دوباره ارسال نمی‌شود.
 */
class SmsMessage extends Model
{
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_TEMPLATE = 'template';
    public const TYPE_TEXT = 'text';

    protected $fillable = [
        'mobile',
        'contact_id',
        'broadcast_id',
        'user_id',
        'template_key',
        'type',
        'body',
        'category',
        'provider',
        'status',
        'attempts',
        'available_at',
        'provider_message_id',
        'cost',
        'response',
        'parameters',
        'variables',
        'dedupe_key',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'variables' => 'array',
            'response' => 'array',
            'cost' => 'decimal:2',
            'available_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(SmsBroadcast::class, 'broadcast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

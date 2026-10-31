<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemUpdate extends Model
{
    public const APPLYING = 'applying';
    public const FILES_APPLIED = 'files_applied';
    public const COMPLETED = 'completed';
    public const FAILED = 'failed';
    public const ROLLED_BACK = 'rolled_back';

    public const STATUSES = [
        self::APPLYING => 'در حال جایگزینی فایل‌ها',
        self::FILES_APPLIED => 'در انتظار تکمیل (migration و کش)',
        self::COMPLETED => 'انجام شد',
        self::FAILED => 'ناموفق (فایل‌ها برگردانده شد)',
        self::ROLLED_BACK => 'به نسخه قبل برگردانده شد',
    ];

    public const METHODS = [
        'panel' => 'پنل',
        'cli' => 'Cron / خط فرمان',
        'manual' => 'File Manager',
    ];

    protected $fillable = [
        'from_version',
        'to_version',
        'package',
        'method',
        'status',
        'user_id',
        'backup_file',
        'summary',
        'error',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'summary' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::APPLYING, self::FILES_APPLIED], true);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::ROLLED_BACK => 'secondary',
            default => 'warning',
        };
    }
}

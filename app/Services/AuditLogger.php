<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ثبت ممیزی عملیات مهم (مرحله ۵۵): چه کسی، چه زمانی، روی چه چیزی، با چه داده‌ای.
 */
class AuditLogger
{
    public function log(Model $subject, string $action, array $data = [], User|Contact|null $actor = null): AuditLog
    {
        $actor ??= auth('web')->user() ?? auth('contact')->user();

        return AuditLog::create([
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'action' => $action,
            'user_id' => $actor instanceof User ? $actor->id : null,
            'contact_id' => $actor instanceof Contact ? $actor->id : null,
            'data' => $data ?: null,
            'ip' => request()?->ip(),
        ]);
    }

    public function for(Model $subject)
    {
        return AuditLog::where('subject_type', $subject->getMorphClass())
            ->where('subject_id', $subject->getKey())
            ->with(['user', 'contact'])
            ->latest('id')
            ->get();
    }
}

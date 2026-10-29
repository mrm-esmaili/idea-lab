<?php

namespace App\Models;

use App\Services\ContactSegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * گروه دستی: اعضای ثبت‌شده. گروه پویا: قواعد ذخیره می‌شوند و عضویت
 * هنگام خواندن محاسبه می‌شود؛ بنابراین عضویت هرگز با داده ناسازگار نمی‌شود.
 */
class ContactGroup extends Model
{
    public const TYPE_MANUAL = 'manual';
    public const TYPE_DYNAMIC = 'dynamic';

    protected $fillable = [
        'name',
        'description',
        'type',
        'rules',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
        ];
    }

    public function manualMembers(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'contact_group_members')
            ->withPivot(['added_by', 'created_at']);
    }

    public function isDynamic(): bool
    {
        return $this->type === self::TYPE_DYNAMIC;
    }

    /**
     * پرس‌وجوی اعضای فعلی گروه.
     */
    public function membersQuery(): Builder
    {
        if ($this->isDynamic()) {
            return app(ContactSegment::class)->apply(Contact::query(), $this->rules ?? []);
        }

        return Contact::query()->whereIn(
            'contacts.id',
            fn ($query) => $query->select('contact_id')
                ->from('contact_group_members')
                ->where('contact_group_id', $this->id)
        );
    }
}

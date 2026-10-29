<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * مخاطب عمومی (خریدار/علاقه‌مند). جدا از User که فقط مدیران پنل‌اند؛
 * ورود مخاطب با guard جداگانه «contact» و هیچ دسترسی مدیریتی نمی‌دهد.
 */
class Contact extends Authenticatable
{
    protected $fillable = [
        'mobile',
        'name',
        'email',
    ];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'marketing_sms_consent' => 'boolean',
            'marketing_consent_at' => 'datetime',
            'marketing_opted_out_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * ورود فقط با OTP است؛ رمز عبور وجود ندارد.
     */
    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function preorders(): HasMany
    {
        return $this->hasMany(Preorder::class);
    }

    public function setMarketingConsent(bool $consent, string $source): void
    {
        if ($consent === $this->marketing_sms_consent) {
            return;
        }

        $this->forceFill($consent
            ? ['marketing_sms_consent' => true, 'marketing_consent_at' => now(), 'marketing_consent_source' => $source, 'marketing_opted_out_at' => null]
            : ['marketing_sms_consent' => false, 'marketing_opted_out_at' => now()]
        )->save();
    }
}

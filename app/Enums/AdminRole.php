<?php

namespace App\Enums;

enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Manager = 'manager';
    case Finance = 'finance';
    case Support = 'support';

    public const PERMISSIONS = [
        'dashboard.view',
        'ideas.manage',
        'leads.manage',
        'preorders.manage',
        'finance.manage',
        'contacts.manage',
        'contacts.export',
        'sms.send',
        'sms.broadcast',
        'users.manage',
        'settings.manage',
    ];

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مدیر کل',
            self::Manager => 'مدیر محصول',
            self::Finance => 'کارشناس مالی',
            self::Support => 'پشتیبانی',
        };
    }

    /**
     * @return list<string>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::SuperAdmin => self::PERMISSIONS,

            self::Manager => [
                'dashboard.view',
                'ideas.manage',
                'leads.manage',
                'preorders.manage',
                'contacts.manage',
                'contacts.export',
                'sms.send',
                'sms.broadcast',
            ],

            self::Finance => [
                'dashboard.view',
                'preorders.manage',
                'finance.manage',
            ],

            self::Support => [
                'dashboard.view',
                'leads.manage',
                'preorders.manage',
                'contacts.manage',
                'sms.send',
            ],
        };
    }

    public function can(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }
}

<?php

/*
| منوی پنل مدیریت
|
| هر آیتم: label، icon (Bootstrap Icons)، route، permission و active
| (الگوهای نام مسیر برای حالت فعال). آیتمی که کاربر مجوزش را ندارد
| نمایش داده نمی‌شود و گروهی که آیتم قابل‌نمایش ندارد پنهان می‌شود.
*/

return [
    [
        'items' => [
            [
                'label' => 'داشبورد',
                'icon' => 'bi-speedometer2',
                'route' => 'admin.dashboard',
                'permission' => 'dashboard.view',
                'active' => ['admin.dashboard'],
            ],
        ],
    ],

    [
        'header' => 'ایده‌ها',
        'items' => [
            [
                'label' => 'فهرست ایده‌ها',
                'icon' => 'bi-lightbulb',
                'route' => 'ideas.index',
                'permission' => 'ideas.manage',
                'active' => ['ideas.index', 'ideas.show', 'ideas.edit', 'ideas.settings', 'ideas.refund-policy', 'landing-pages.*'],
            ],
            [
                'label' => 'ایده جدید',
                'icon' => 'bi-plus-circle',
                'route' => 'ideas.create',
                'permission' => 'ideas.manage',
                'active' => ['ideas.create'],
            ],
        ],
    ],

    [
        'header' => 'بازاریابی',
        'items' => [
            [
                'label' => 'کمپین‌ها',
                'icon' => 'bi-megaphone',
                'route' => 'admin.campaigns.index',
                'permission' => 'ideas.manage',
                'active' => ['admin.campaigns.*'],
            ],
        ],
    ],

    [
        'header' => 'مخاطبان و فروش',
        'items' => [
            [
                'label' => 'مخاطبان',
                'icon' => 'bi-person-vcard',
                'route' => 'admin.contacts.index',
                'permission' => 'contacts.manage',
                'active' => ['admin.contacts.*'],
            ],
            [
                'label' => 'گروه‌ها',
                'icon' => 'bi-collection',
                'route' => 'admin.groups.index',
                'permission' => 'contacts.manage',
                'active' => ['admin.groups.*'],
            ],
            [
                'label' => 'سرنخ‌ها',
                'icon' => 'bi-person-lines-fill',
                'route' => 'admin.leads.index',
                'permission' => 'leads.manage',
                'active' => ['admin.leads.*'],
            ],
            [
                'label' => 'پیش‌خریدها',
                'icon' => 'bi-bag-check',
                'route' => 'admin.preorders.index',
                'permission' => 'preorders.manage',
                'active' => ['admin.preorders.*'],
            ],
        ],
    ],

    [
        'header' => 'امور مالی',
        'items' => [
            [
                'label' => 'پرداخت‌ها',
                'icon' => 'bi-credit-card',
                'route' => 'admin.payments.index',
                'permission' => 'finance.manage',
                'active' => ['admin.payments.*'],
            ],
            [
                'label' => 'بازپرداخت‌ها',
                'icon' => 'bi-arrow-counterclockwise',
                'route' => 'admin.refunds.index',
                'permission' => 'finance.manage',
                'active' => ['admin.refunds.*'],
            ],
            [
                'label' => 'هزینه‌ها',
                'icon' => 'bi-receipt',
                'route' => 'admin.expenses.index',
                'permission' => 'finance.manage',
                'active' => ['admin.expenses.*'],
            ],
            [
                'label' => 'گزارش مالی',
                'icon' => 'bi-graph-up',
                'route' => 'admin.finance.index',
                'permission' => 'finance.manage',
                'active' => ['admin.finance.*'],
            ],
        ],
    ],

    [
        'header' => 'پیامک',
        'items' => [
            [
                'label' => 'ارسال گروهی',
                'icon' => 'bi-megaphone',
                'route' => 'admin.sms.broadcasts.index',
                'permission' => 'sms.broadcast',
                'active' => ['admin.sms.broadcasts.*'],
            ],
            [
                'label' => 'سابقه پیامک',
                'icon' => 'bi-chat-left-text',
                'route' => 'admin.sms.messages.index',
                'permission' => 'sms.send',
                'active' => ['admin.sms.messages.*'],
            ],
        ],
    ],

    [
        'header' => 'تنظیمات',
        'items' => [
            [
                'label' => 'کاربران پنل',
                'icon' => 'bi-people',
                'route' => 'admin.users.index',
                'permission' => 'users.manage',
                'active' => ['admin.users.*'],
            ],
            [
                'label' => 'قالب‌های پیامک',
                'icon' => 'bi-chat-dots',
                'route' => 'admin.sms.index',
                'permission' => 'settings.manage',
                'active' => ['admin.sms.index', 'admin.sms.templates.*'],
            ],
        ],
    ],
];

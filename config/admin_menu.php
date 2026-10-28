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
                'active' => ['ideas.index', 'ideas.show', 'landing-pages.*'],
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
        'header' => 'مخاطبان و فروش',
        'items' => [
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
];

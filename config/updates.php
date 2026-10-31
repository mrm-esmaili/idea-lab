<?php

return [
    // به‌روزرسانی از پنل؛ با false صفحه آن فقط پیام غیرفعال بودن نشان می‌دهد
    'enabled' => (bool) env('APP_UPDATES_ENABLED', true),

    // بسته‌های بارگذاری‌شده، نسخه پشتیبان فایل‌ها و manifest نصب‌شده
    'directory' => storage_path('app/updates'),

    // حداکثر حجم بارگذاری بسته از پنل (محدودیت upload_max_filesize هاست هم اعمال می‌شود)
    'max_upload_mb' => (int) env('UPDATE_MAX_UPLOAD_MB', 64),

    // ریشه پروژه‌ای که به‌روزرسانی می‌شود؛ فقط در آزمون‌ها تغییر می‌کند
    'target' => null,
];

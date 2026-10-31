<?php

return [
    // تعداد پشتیبان‌های روزانه نگه‌داشته‌شده (قدیمی‌ترها حذف می‌شوند)
    'keep' => (int) env('BACKUP_KEEP', 14),

    // محل فایل‌های پشتیبان؛ بیرون از دسترس وب
    'directory' => storage_path('app/backups'),
];

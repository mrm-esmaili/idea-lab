<?php

/*
 * سیاست ارسال پیامک (صف، محدودیت نرخ، تلاش دوباره و پیامک تبلیغاتی).
 * تنظیمات اتصال SMS.ir در config/services.php است.
 */

[$promoFrom, $promoTo] = array_map('intval', explode('-', (string) env('SMS_PROMO_HOURS', '9-21')) + [1 => 21]);

return [
    // حداکثر پیامک ارسالی در هر دقیقه (همه انواع)
    'rate_per_minute' => (int) env('SMS_RATE_PER_MINUTE', 60),

    // حداکثر پیامک پردازش‌شده در هر اجرای صف
    'batch_size' => (int) env('SMS_BATCH_SIZE', 100),

    // حداکثر گیرنده در هر درخواست ارسال گروهی به ارائه‌دهنده
    'bulk_chunk' => 100,

    // تلاش دوباره فقط برای خطای گذرا (عدم دسترسی یا خطای سرور ارائه‌دهنده)
    'max_attempts' => 3,
    'retry_after_minutes' => 5,

    // پیامی که این مدت در حالت «در حال ارسال» مانده (مثلاً قطع اجرای PHP)
    // با وضعیت ناموفق و «نتیجه نامعلوم» بسته می‌شود و خودکار تکرار نمی‌شود
    'stuck_after_minutes' => 15,

    // پیامک تبلیغاتی فقط در این ساعات (به وقت APP_DISPLAY_TIMEZONE) ارسال می‌شود
    'promo_hours' => ['from' => $promoFrom, 'to' => $promoTo],

    // متن لغو اشتراک که به انتهای هر پیامک تبلیغاتی افزوده می‌شود
    'promo_footer' => env('SMS_PROMO_FOOTER', 'لغو۱۱'),

    'max_text_length' => 600,

    // حداکثر طول مقدار هر پارامتر قالب در SMS.ir؛ مقدار بلندتر خودکار کوتاه می‌شود
    'template_param_max' => (int) env('SMS_TEMPLATE_PARAM_MAX', 25),
];

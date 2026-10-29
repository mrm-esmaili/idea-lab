<?php

return [
    'length' => 6,

    // اعتبار هر کد (ثانیه)
    'ttl' => (int) env('OTP_TTL_SECONDS', 120),

    // فاصله مجاز بین دو درخواست کد برای یک شماره (ثانیه)
    'resend_cooldown' => (int) env('OTP_RESEND_COOLDOWN', 60),

    'max_sends_per_mobile_per_hour' => 5,
    'max_sends_per_ip_per_hour' => 20,

    // تلاش ناموفق مجاز برای هر کد
    'max_attempts' => 5,

    // حداکثر عمر نشست مخاطب پس از ورود (ساعت)، مستقل از فعالیت
    'contact_session_hours' => (int) env('CONTACT_SESSION_HOURS', 24),
];

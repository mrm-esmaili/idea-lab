# Idea Lab

سامانه اعتبارسنجی ایده: صفحه فرود، رهگیری بازدید و UTM، ثبت سرنخ، پیش‌خرید و پرداخت زرین‌پال.

- Laravel 12 — PHP 8.2+ (هاست: PHP 8.4)
- پایگاه داده هدف: MariaDB 11.4 / utf8mb4
- نقشه راه و gap analysis: `agentic_brain/GAP_ANALYSIS.md`

## راه‌اندازی محلی

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## نقش‌های مدیریتی

دسترسی پنل فقط برای کاربرانی است که ستون `role` آن‌ها مقدار دارد. کاربر بدون نقش (مثلاً مخاطب در آینده) وارد پنل نمی‌شود.

| نقش | مقدار | دسترسی |
|---|---|---|
| مدیر کل | `super_admin` | همه بخش‌ها |
| مدیر محصول | `manager` | داشبورد، ایده‌ها و صفحات فرود، سرنخ‌ها، پیش‌خریدها |
| کارشناس مالی | `finance` | داشبورد، پیش‌خریدها، امور مالی |
| پشتیبانی | `support` | داشبورد، سرنخ‌ها، پیش‌خریدها |

تعریف نقش‌ها و مجوزها: `app/Enums/AdminRole.php`.
هنگام اجرای migration نقش، همه کاربران موجود `super_admin` می‌شوند.

## قالب مدیریتی

AdminLTE 4 (RTL) با Bootstrap 5، Bootstrap Icons و فونت Vazirmatn. فایل‌های build‌شده در `public/vendor` قرار دارند و به npm یا CDN نیازی نیست.

- layout مشترک: `resources/views/layouts/admin.blade.php`
- منو: `config/admin_menu.php` (هر آیتم با مجوز نقش نمایش داده می‌شود)
- برچسب وضعیت‌ها: `config/statuses.php`

## پیامک (SMS.ir) و ورود مخاطب

مخاطبان با شماره موبایل و کد یک‌بارمصرف در `/account` وارد می‌شوند. برای فعال‌سازی ارسال واقعی:

1. در پنل SMS.ir یک قالب «ارسال سریع» برای کد ورود بسازید، مثلاً: `کد ورود شما: #CODE#`
2. پس از تأیید قالب، در پنل مدیریت ← تنظیمات ← پیامک، شناسه قالب و نام پارامتر (`CODE`) را وارد کنید.
3. در `.env` سرور: `SMS_DRIVER=smsir` و `SMSIR_API_KEY=...` (کلید هرگز در Git قرار نگیرد).

تا انجام این مراحل، `SMS_DRIVER=log` فقط برای توسعه محلی است: کد در `storage/logs/laravel.log` نوشته می‌شود و در محیط production خطا می‌دهد.

## امنیت

فایل `.env`، کلیدهای API، رمزها و داده شخصی واقعی هرگز در Git قرار نمی‌گیرند.

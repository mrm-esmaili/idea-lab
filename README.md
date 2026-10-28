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

## امنیت

فایل `.env`، کلیدهای API، رمزها و داده شخصی واقعی هرگز در Git قرار نمی‌گیرند.

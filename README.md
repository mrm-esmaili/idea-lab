# Idea Lab — ایده‌لب

سامانه اعتبارسنجی ایده: صفحه فرود و کمپین با رهگیری UTM، ثبت مخاطب و باشگاه مشتریان، پیش‌خرید و پرداخت زرین‌پال، بازپرداخت سه‌مرحله‌ای، پیامک SMS.ir، آزمایش و ثبت تصمیم، و گزارش‌های مالی و قیف تبدیل.

- Laravel 12، PHP 8.2+ (هاست: PHP 8.4)
- MariaDB 10.11+/11.4 یا MySQL 8 (utf8mb4)
- پنل AdminLTE 4 راست‌چین، تاریخ شمسی، مبالغ به تومان؛ بدون npm/CDN (فایل‌ها در `public/vendor`)

## مستندات

| سند | محتوا |
|---|---|
| [`ADMIN_GUIDE.md`](ADMIN_GUIDE.md) | راهنمای مدیر: نقش‌ها و کار روزمره با هر بخش پنل |
| [`DEPLOYMENT.md`](DEPLOYMENT.md) | استقرار روی cPanel بدون SSH، متغیرهای محیط، به‌روزرسانی و بازیابی |
| [`OPERATIONS_CHECKLIST.md`](OPERATIONS_CHECKLIST.md) | چک‌لیست پیش از راه‌اندازی، روزانه، هفتگی و ماهانه |
| [`PROJECT_STATUS.md`](PROJECT_STATUS.md) | وضعیت پروژه، قابلیت‌های تکمیل‌شده و محدودیت‌های باقی‌مانده |
| [`agentic_brain/ARCHITECTURE.md`](agentic_brain/ARCHITECTURE.md) | معماری، قراردادهای ثابت و مرز قابلیت‌های تحلیلی آینده |
| [`agentic_brain/GAP_ANALYSIS.md`](agentic_brain/GAP_ANALYSIS.md) | گزارش فنی هر گام نقشه راه |

## توسعه محلی

```bash
composer install
cp .env.example .env        # SMS_DRIVER=log: کد ورود در storage/logs نوشته می‌شود
php artisan key:generate
php artisan migrate
php artisan serve
```

اولین مدیر کل: `INSTALL_TOKEN` را در `.env` بگذارید و `/install?token=...` را باز کنید.

## آزمون‌ها

```bash
php artisan test                                   # SQLite در حافظه
DB_CONNECTION=mariadb DB_DATABASE=idealab_test DB_USERNAME=... DB_PASSWORD=... php artisan test
```

آزمون عملیاتی انتها‌به‌انتها در مرورگر (نصب، ایده تا پرداخت، بازپرداخت، SMS.ir) با درگاه و SMS.ir شبیه‌سازی‌شده — نیازمند Node و Playwright:

```bash
E2E_DB_DATABASE=idealab_e2e E2E_DB_USERNAME=... E2E_DB_PASSWORD=... tests/e2e/run.sh
```

## امنیت

فایل `.env`، کلیدهای API، رمزها، پشتیبان‌ها و داده شخصی واقعی هرگز در Git قرار نمی‌گیرند.

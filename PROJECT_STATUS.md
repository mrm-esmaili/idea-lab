# وضعیت پروژه ایده‌لب

> این فایل در پایان هر قابلیت به‌روزرسانی می‌شود. جزئیات فنی هر گام در `agentic_brain/GAP_ANALYSIS.md` است.

| مورد | مقدار |
|---|---|
| آخرین به‌روزرسانی | ۱۴۰۵/۰۸/۰۷ |
| شاخه | `claude/new-session-ebbvr0` |
| آخرین قابلیت تکمیل‌شده | گام G — حساب مخاطب و ورود با OTP (مراحل ۲۷ تا ۳۲) |
| تست‌ها | ۱۰۸ تست، همه پاس روی SQLite و MariaDB 10.11 (strict) |
| محیط هدف | MariaDB 11.4، PHP 8.4، cPanel بدون SSH و npm |

## قابلیت‌های تکمیل‌شده

| گام | مراحل نقشه راه | قابلیت | کامیت |
|---|---|---|---|
| — | ۱ تا ۱۰ | هسته اولیه (پیش از این مجموعه): ایده، صفحه فرود، رهگیری، سرنخ، پیش‌خرید، زرین‌پال | `634b49b` |
| — | ۱۱ | بررسی مخزن و gap analysis | `9981474` |
| A | ۱۸ (بخشی)، ۸۷ | نقش‌های مدیریتی (مدیر کل، مدیر محصول، مالی، پشتیبانی)، نرمال‌سازی موبایل، رفع خطای UTM در MariaDB، حذف رمز از مستندات | `bea2849` |
| B | ۷۹، ۸۰، ۸۲ | قالب مدیریتی AdminLTE 4 راست‌چین، منوی دسته‌بندی‌شده بر اساس نقش، اجزای مشترک | `e156540` |
| C | ۱۲ تا ۱۶، ۱۸ | ویرایش و بایگانی ایده، چرخه وضعیت با تاریخچه، آمار ایده، ویرایش/انتشار و محتوای صفحه فرود، مدیریت کاربران پنل | `62cdcc2` |
| D | ۱۷، ۴۹ (ساختار) | تنظیمات ایده (بودجه، بازه، حدنصاب، مبلغ)، قرارداد پول ریال/تومان، نسخه‌های تغییرناپذیر شرایط بازپرداخت، snapshot مبلغ و شرایط در سفارش | `1ea8266` |
| E | ۴۱ تا ۴۸ | تفکیک وضعیت پرداخت از پیگیری، پرداخت تکرارناپذیر، رسید، استعلام دوباره، گزارش پرداخت‌ها و تشخیص پرداخت تکراری | `d8b77ea` |
| F | ۱۹، ۲۰، ۲۴ تا ۲۶ | کمپین‌ها و لینک رهگیری، قاعده انتساب، گزارش کمپین، بهبود صفحه فرود عمومی، پیام‌های خطای فارسی | `86e134f`، `5c5f29e` |
| G | ۲۷ تا ۳۲ | لایه پیامک SMS.ir با قالب قابل تنظیم از پنل، مخاطب جدا از مدیر، ورود با OTP، حساب مخاطب، رضایت پیامک تبلیغاتی | `862bb0a` |

## آخرین قابلیت: گام G — فایل‌های تغییرکرده

**جدید**
- کنترلرها: `app/Http/Controllers/Account/AccountController.php`، `app/Http/Controllers/Account/AuthController.php`، `app/Http/Controllers/Admin/SmsTemplateController.php`
- middleware: `app/Http/Middleware/EnsureContactSessionIsFresh.php`
- مدل‌ها: `app/Models/Contact.php`، `OtpCode.php`، `SmsMessage.php`، `SmsTemplate.php`
- سرویس‌ها: `app/Services/ContactResolver.php`، `app/Services/Otp/OtpService.php`، `OtpException.php`، `app/Services/Sms/SmsService.php`، `SmsProvider.php`، `SmsIrProvider.php`، `LogSmsProvider.php`، `SmsException.php`
- پیکربندی: `config/otp.php`
- migration: `2026_11_02_100000_create_sms_tables.php`، `2026_11_02_100100_create_contacts_table.php`، `2026_11_02_100200_create_otp_codes_table.php`
- نما: `resources/views/layouts/public.blade.php`، `resources/views/account/{login,verify,dashboard}.blade.php`، `resources/views/admin/sms/{index,edit}.blade.php`، `public/css/public.css`
- تست: `tests/Feature/ContactOtpTest.php`

**تغییریافته**
`.env.example`، `README.md`، `app/Enums/AdminRole.php`، `app/Http/Controllers/LeadController.php`، `app/Http/Controllers/PreorderController.php`، `app/Http/Middleware/EnsureUserIsAdmin.php`، `app/Models/Lead.php`، `app/Models/Preorder.php`، `app/Providers/AppServiceProvider.php`، `app/Support/PhoneNumber.php`، `bootstrap/app.php`، `config/{admin_menu,admin_permissions,auth,services,statuses}.php`، `resources/views/landing-pages/show.blade.php`، `resources/views/payments/receipt.blade.php`، `routes/web.php`

## خطاها و محدودیت‌های باقی‌مانده

| مورد | توضیح | برنامه |
|---|---|---|
| قالب OTP در SMS.ir | شناسه قالب هنوز تنظیم نشده؛ تا تنظیم آن ورود مخاطب روی سرور ممکن نیست | اقدام مالک (پنل ← تنظیمات ← پیامک) |
| آزمون واقعی درگاه‌ها | اتصال به sandbox زرین‌پال و SMS.ir از محیط توسعه ممکن نبود؛ همه مسیرها با شبیه‌سازی آزموده شده‌اند | مراحل ۹۷ و ۹۹ |
| هم‌زمانی پرداخت | قفل ردیف در شروع پرداخت زیر بار هم‌زمان واقعی MariaDB آزمایش نشده | مرحله ۹۷ |
| تاریخ شمسی | همه تاریخ‌ها میلادی (به وقت تهران) نمایش داده می‌شوند | گام L |
| ستون قدیمی `preorders.amount` | دیگر خوانده یا نوشته نمی‌شود ولی هنوز حذف نشده | مرحله ۸۵ |
| هزینه واقعی کمپین | هزینه هر سرنخ فعلاً از بودجه برنامه‌ریزی‌شده حساب می‌شود | مرحله ۵۶ |
| داشبورد کلی | آمار پرداخت و کارهای نیازمند رسیدگی هنوز در داشبورد اصلی نیست | مرحله ۷۳ |
| اجرای migration روی هاست | روش اجرای migration بدون SSH هنوز تعیین نشده | مرحله ۹۵ |

## اقدامات لازم روی هاست (تا این لحظه)

1. فعال بودن افزونه `pdo_mysql` در cPanel ← Select PHP Version.
2. اجرای همه migrationها.
3. قابل نوشتن بودن پوشه `public/uploads`.
4. تنظیم شرایط بازپرداخت هر ایده (بدون آن فرم پیش‌خرید نمایش داده نمی‌شود).
5. در `.env`: `SMS_DRIVER=smsir`، `SMSIR_API_KEY`، و در صورت نیاز `APP_PRIVACY_URL`.
6. تغییر رمز عبوری که پیش‌تر در تاریخچه Git ثبت شده بود.

## قدم بعدی

**گام H — باشگاه مشتریان (مراحل ۳۳ تا ۴۰):** فهرست و پرونده یکپارچه مخاطبان، علاقه چندبه‌چند مخاطب و ایده، گروه‌های دستی و خودکار، تاریخچه تعامل‌ها، و خروجی با کنترل دسترسی.

# DOCS

## Step1: Core DB Create

###  Chart:
```
Idea
│
└── LandingPage
    │
    └── Campaign
```

### laravel install
```bash
composer create-project laravel/laravel idea-lab
cd idea-lab
php artisan serve
```

### Migration:
```bash
php artisan make:model Idea -m
php artisan make:model LandingPage -m
php artisan make:model Campaign
php artisan migrate
```

### Models:
- Idea
- LandingPage
- Campaign

### Example For Table -> landing_pages -> json('content')
```json
{
    "hero": {
        "title": "باشگاهت را هوشمند مدیریت کن",
        "description": "..."
    },
    "features": [
        "مدیریت شاگردان",
        "مدیریت پرداخت",
        "گزارش‌گیری"
    ],
    "pricing": {
        "price": 990000
    }
}
```

### chart
Landing Page
│
├── تبلیغ دیوار
├── تبلیغ اینستاگرام
├── تبلیغ تلگرام
└── تبلیغ گوگل

ایده جدید
    ↓
ساخت Landing Page
    ↓
تولید خودکار URL
    ↓
مشاهده صفحه

---

## Step2: Idea & Landing Page Management

### 2.1 - Controllers
```bash
php artisan make:controller IdeaController
php artisan make:controller LandingPageController
```

### chart
GET     /ideas
GET     /ideas/create
POST    /ideas
GET     /ideas/{idea}

### test
```bash
php artisan serve
```

http://127.0.0.1:8000/ideas

+ ایجاد ایده جدید

نام ایده:
سیستم مدیریت باشگاه

توضیحات:
سیستم آنلاین مدیریت باشگاه‌های ورزشی

### chart
/ideas
    ↓
ایجاد ایده
    ↓
/ideas/{idea}
    ↓
ساخت Landing Page
    ↓
Landing Page داخل ایده

## Step3: Landing Page Public

### Controller Create
```bash
php artisan make:controller PublicLandingPageController
```

## Step4: Tracking System

### chart
LandingPage
    │
    └── LandingPageEvent
             │
             └── Visitor

Visitor
    └── چندین بازدید و رویداد

### Migrations
```bash
php artisan make:model Visitor -m
php artisan make:model LandingPageEvent -m
php artisan migrate
```

### Test System
http://127.0.0.1:8000/l/test?utm_source=divar&utm_medium=paid&utm_campaign=test01
```bash
php artisan tinker

\App\Models\LandingPageEvent::where(
    'event_name',
    'page_view'
)->count();

\App\Models\LandingPageEvent::latest()
    ->take(10)
    ->get();

\App\Models\Visitor::count();

\App\Models\Visitor::select(
    'uuid',
    'first_source',
    'first_medium',
    'first_campaign',
    'first_seen_at',
    'last_seen_at'
)->get();
```

```
https://example.com/l/clubyar?utm_source=divar&utm_medium=paid&utm_campaign=clubyar01
https://example.com/l/clubyar?utm_source=instagram&utm_medium=social&utm_campaign=clubyar01
https://example.com/l/clubyar?utm_source=telegram&utm_medium=social&utm_campaign=clubyar01
```

## Step5: Register Form

### Chart:
تبلیغ

دیوار، اینستاگرام، تلگرام و سایر کانال‌ها

Landing Page

ثبت بازدید و منبع ورودی

فرم علاقه‌مندی

نام، شماره تماس و اطلاعات موردنیاز

مدیریت Leadها

پیگیری، سنجش علاقه و ثبت پیش‌پرداخت

### Leads DB
```bash
php artisan make:model Lead -m
```

### Stats
- new
- contacted
- interested
- prepaid
- customer
- rejected

### Migrate
```bash
php artisan migrate
```

### Debug
- delete repeated migration file
- delete sql.lite database
```bash
php artisan tinker
Schema::dropIfExists('leads');
exit
php artisan migrate
php artisan migrate:status
```

### Controller
```bash
php artisan make:controller LeadController
```

### Test
```bash
php artisan serve
php artisan tinker

\App\Models\Lead::count();

\App\Models\Lead::latest()->first();

\App\Models\LandingPageEvent::where(
    'event_name',
    'form_submit'
)->count();

\App\Models\LandingPage::where(
    'slug',
    'test'
)->first()?->leads()->count();
```

### Test Data Process
```bash
$page = \App\Models\LandingPage::where(
    'slug',
    'test'
)->firstOrFail();

$views = $page->events()
    ->where('event_name', 'page_view')
    ->count();

$leads = $page->leads()->count();

$conversionRate = $views > 0
    ? round(($leads / $views) * 100, 2)
    : 0;

[
    'views' => $views,
    'leads' => $leads,
    'conversion_rate' => $conversionRate,
];
```

## Step6: Admin Panel Auth

### CMD
```bash
php artisan make:controller AuthController
php artisan make:controller Admin/LeadManagementController
```

### Insert Admin Data
```bash
php artisan tinker

\App\Models\User::create([
    'name' => 'admin',
    'email' => 'admin@example.com',
    'password' => \Illuminate\Support\Facades\Hash::make(
        'CHANGE_ME'
    ),
    'role' => 'super_admin',
]);
```

> رمز عبور واقعی هرگز در این فایل یا هر فایل دیگری در Git نوشته نشود.

### Test Addresses
- /login
- /ideas
- /admin/leads
- /l/test

## Step7: Dashboard

### CMD
```bash
php artisan make:controller Admin/DashboardController
```

### Test
```bash
php artisan route:list
php artisan view:clear
php artisan serve
```
http://127.0.0.1:8000/admin

## Step8: preorder

### LandingPageEventController
```bash
php artisan make:controller LandingPageEventController
```

## Step9: PreorderController

## Step10: ZarinPal

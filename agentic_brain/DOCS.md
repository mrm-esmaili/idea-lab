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

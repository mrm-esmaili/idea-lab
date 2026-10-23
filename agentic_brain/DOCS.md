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

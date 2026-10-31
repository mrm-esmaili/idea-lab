<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="same-origin">

    <title>@yield('title') | {{ app(\App\Services\SiteSettings::class)->companyName() ?? 'ایده‌لب' }}</title>

    <link rel="stylesheet" href="{{ asset('css/public.css') }}">
</head>
<body>
    <header class="topbar">
        <div class="container">
            <a href="{{ auth('contact')->check() ? route('account.dashboard') : route('account.login') }}" class="brand">{{ auth('contact')->check() ? 'سفارش‌های من' : 'پیگیری سفارش و ورود' }}</a>

            @auth('contact')
                <form method="POST" action="{{ route('account.logout') }}">
                    @csrf
                    <button type="submit" class="button-link">خروج</button>
                </form>
            @endauth
        </div>
    </header>

    <main>
        <div class="container">
            @if (session('success'))
                <div class="alert alert-success" role="status">{{ session('success') }}</div>
            @endif

            @if (session('notice'))
                <div class="alert alert-notice" role="status">{{ session('notice') }}</div>
            @endif

            @yield('content')
        </div>
    </main>

    <x-site-footer />

    @stack('scripts')
</body>
</html>

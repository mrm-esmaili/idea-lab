<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>نصب ایده‌لب</title>
    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/adminlte.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body class="bg-body-tertiary">
<main class="container py-5" style="max-width: 560px">
    <h1 class="h4 mb-3">نصب ایده‌لب</h1>

    @if ($done)
        <div class="alert alert-success">
            <p class="mb-2">پایگاه داده آماده و مدیر کل ساخته شد.</p>
            <ol class="mb-0 small">
                <li><strong>همین حالا</strong> خط <span class="ltr">INSTALL_TOKEN</span> را از فایل <span class="ltr">.env</span> حذف کنید.</li>
                <li>وارد پنل شوید و صفحه «وضعیت سیستم» را بررسی کنید.</li>
            </ol>
        </div>
        <a href="{{ route('login') }}" class="btn btn-primary">ورود به پنل</a>
    @else
        <p class="text-body-secondary small">
            این صفحه همه migrationها را اجرا و اولین مدیر کل را می‌سازد. فقط یک بار و تا وقتی مدیر کلی وجود ندارد در دسترس است.
        </p>

        @if ($errors->any())
            <div class="alert alert-danger small" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('install.store') }}" class="card card-body">
            <input type="hidden" name="token" value="{{ $token }}">
            <div class="mb-3">
                <label for="name" class="form-label">نام مدیر کل</label>
                <input id="name" name="name" required maxlength="100" value="{{ $old['name'] ?? '' }}" class="form-control">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">ایمیل (نام کاربری ورود)</label>
                <input id="email" type="email" name="email" required value="{{ $old['email'] ?? '' }}" class="form-control ltr">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">رمز عبور</label>
                <input id="password" type="password" name="password" required minlength="12" autocomplete="new-password" class="form-control ltr">
                <div class="form-text">دست‌کم ۱۲ نویسه، شامل حرف و عدد.</div>
            </div>
            <div class="mb-3">
                <label for="password_confirmation" class="form-label">تکرار رمز عبور</label>
                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="form-control ltr">
            </div>
            <button type="submit" class="btn btn-primary">اجرای migration و ساخت مدیر کل</button>
        </form>
    @endif
</main>
</body>
</html>

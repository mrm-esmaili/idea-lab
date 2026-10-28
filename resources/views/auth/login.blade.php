<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light">

    <title>ورود به پنل مدیریت | ایده‌لب</title>

    <link rel="stylesheet" href="{{ asset('vendor/adminlte/css/adminlte.rtl.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>

<body class="login-page bg-body-secondary">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <h1 class="h3 mb-0">
                <i class="bi bi-rocket-takeoff"></i>
                ایده‌لب
            </h1>
        </div>

        <div class="card-body login-card-body">
            <p class="login-box-msg">ورود به پنل مدیریت</p>

            @if ($errors->any())
                <div class="alert alert-danger" role="alert">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.submit') }}">
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">ایمیل</label>
                    <div class="input-group">
                        <input
                            id="email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            autocomplete="username"
                            class="form-control ltr"
                            required
                            autofocus
                        >
                        <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">رمز عبور</label>
                    <div class="input-group">
                        <input
                            id="password"
                            type="password"
                            name="password"
                            autocomplete="current-password"
                            class="form-control ltr"
                            required
                        >
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100">ورود</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>

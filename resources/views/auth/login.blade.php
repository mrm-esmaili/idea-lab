<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ورود به پنل مدیریت</title>
</head>
<body>

    <h1>ورود به پنل مدیریت</h1>

    @if ($errors->any())
        <div role="alert">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ route('login.submit') }}">
        @csrf

        <div>
            <label for="email">ایمیل</label>
            <input
                id="email"
                type="email"
                name="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                autofocus
            >
        </div>

        <div>
            <label for="password">رمز عبور</label>
            <input
                id="password"
                type="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit">ورود</button>
    </form>

</body>
</html>

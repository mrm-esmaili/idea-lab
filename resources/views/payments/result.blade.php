<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>نتیجه پرداخت</title>

    <link rel="stylesheet" href="{{ asset('css/public.css') }}">
</head>

<body>
    <main>
        <div class="container">
            <div class="card">
                @if ($status === 'success')
                    <h1>پرداخت با موفقیت تأیید شد</h1>
                    <p>پرداخت شما توسط درگاه تأیید شده است.</p>
                @elseif ($status === 'failed')
                    <h1>پرداخت انجام نشد</h1>
                    <p>پرداخت لغو شده یا توسط درگاه تأیید نشده است.</p>
                @else
                    <h1>بررسی پرداخت امکان‌پذیر نبود</h1>
                    <p>
                        وضعیت پرداخت هنوز قابل تأیید نیست.
                        در صورت کسر وجه، لطفاً دوباره پرداخت نکنید و از راه‌های پشتیبانی زیر با ما تماس بگیرید.
                    </p>
                @endif

                <p class="muted small" style="margin-bottom: 0">
                    وضعیت سفارش‌های خود را با شماره موبایلتان از <a href="{{ route('account.login') }}">پیگیری سفارش</a> ببینید.
                </p>
            </div>
        </div>
    </main>

    <x-site-footer />
</body>
</html>

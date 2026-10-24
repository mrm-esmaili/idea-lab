<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>نتیجه پرداخت</title>
</head>

<body>
    @if ($status === 'success')
        <h1>پرداخت با موفقیت تأیید شد</h1>

        <p>
            پرداخت شما توسط درگاه تأیید شده است.
        </p>

    @elseif ($status === 'failed')
        <h1>پرداخت انجام نشد</h1>

        <p>
            پرداخت لغو شده یا توسط درگاه تأیید نشده است.
        </p>

    @else
        <h1>بررسی پرداخت امکان‌پذیر نبود</h1>

        <p>
            وضعیت پرداخت هنوز قابل تأیید نیست.
            در صورت کسر وجه، لطفاً دوباره پرداخت نکنید
            و برای بررسی تراکنش با پشتیبانی تماس بگیرید.
        </p>
    @endif
</body>
</html>

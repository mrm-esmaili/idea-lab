<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>پرداخت پیش‌خرید</title>

    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: Tahoma, Arial, sans-serif;
            background: #f4f6fa;
            color: #202938;
        }

        .box {
            max-width: 480px;
            margin: 40px auto;
            padding: 24px;
            background: white;
            border-radius: 12px;
        }

        .amount {
            font-size: 24px;
            font-weight: bold;
            margin: 20px 0;
        }

        button {
            width: 100%;
            padding: 14px;
            border: 0;
            border-radius: 8px;
            background: #26364d;
            color: white;
            cursor: pointer;
            font-size: 15px;
        }
    </style>
</head>

<body>
<div class="box">
    <h1>تکمیل پیش‌خرید</h1>

    <p>
        متقاضی:
        {{ $preorder->name }}
    </p>

    <p>
        بابت درخواست پیش‌خرید، مبلغ زیر را پرداخت می‌کنید:
    </p>

    <div class="amount">
        {{ number_format($preorder->amount) }}
        تومان
    </div>

    <form
        method="POST"
        action="{{ route('preorders.pay', ['token' => $preorder->public_token]) }}"
    >
        @csrf

        <button type="submit">
            پرداخت با زرین‌پال
        </button>
    </form>
</div>
</body>
</html>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">

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

        .alert {
            padding: 12px;
            margin-bottom: 16px;
            border-radius: 8px;
            font-size: 14px;
        }

        .alert-error {
            background: #fff0f0;
            color: #a32020;
        }

        .alert-notice {
            background: #fff8e6;
            color: #7a5200;
        }

        .terms {
            margin-bottom: 20px;
            font-size: 14px;
            color: #475569;
        }

        .terms summary {
            cursor: pointer;
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

    @if (session('payment_error'))
        <div class="alert alert-error" role="alert">{{ session('payment_error') }}</div>
    @endif

    @if (session('payment_notice'))
        <div class="alert alert-notice" role="status">{{ session('payment_notice') }}</div>
    @endif

    <p>
        متقاضی:
        {{ $preorder->name }}
    </p>

    <p>
        بابت درخواست پیش‌خرید، مبلغ زیر را پرداخت می‌کنید:
    </p>

    <div class="amount">
        {{ \App\Support\Format::faDigits(\App\Support\Money::formatRialAsToman($preorder->amount_rial)) }}
    </div>

    @if ($preorder->refundPolicy)
        <details class="terms">
            <summary>
                شرایط بازپرداخت پذیرفته‌شده:
                {{ \App\Support\Format::faDigits($preorder->refundPolicy->summary()) }}
            </summary>
            <p>{!! nl2br(e($preorder->refundPolicy->terms)) !!}</p>
        </details>
    @endif

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

<x-site-footer />
</body>
</html>

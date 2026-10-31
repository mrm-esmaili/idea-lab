<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">

    <title>{{ $preorder->isPaid() ? 'رسید پرداخت' : 'وضعیت پرداخت' }}</title>

    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: Tahoma, Arial, sans-serif;
            background: #f4f6fa;
            color: #202938;
            line-height: 1.9;
        }

        .box {
            max-width: 480px;
            margin: 40px auto;
            padding: 24px;
            background: white;
            border-radius: 12px;
        }

        .status {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .status-paid { background: #e8f8ee; color: #176534; }
        .status-pending { background: #fff8e6; color: #7a5200; }
        .status-failed { background: #fff0f0; color: #a32020; }

        dl {
            display: grid;
            grid-template-columns: auto 1fr;
            gap: 8px 16px;
            margin: 0 0 20px;
        }

        dt { color: #64748b; }
        dd { margin: 0; }

        .ltr { direction: ltr; unicode-bidi: isolate; }

        .button {
            display: block;
            padding: 14px;
            border-radius: 8px;
            background: #26364d;
            color: white;
            text-align: center;
            text-decoration: none;
        }

        .muted { color: #64748b; font-size: 14px; }
    </style>
</head>

<body>
<div class="box">
    @if ($preorder->isPaid())
        <h1>رسید پرداخت</h1>

        <div class="status status-paid" role="status">
            پرداخت شما با موفقیت تأیید شد.
        </div>

        @if (in_array($preorder->payment_status, ['refunded', 'partially_refunded'], true))
            <div class="status status-pending" role="status">
                {{ $preorder->payment_status === 'refunded' ? 'مبلغ این سفارش به شما بازپرداخت شده است.' : 'بخشی از مبلغ این سفارش به شما بازپرداخت شده است.' }}
            </div>
        @endif

        <dl>
            <dt>متقاضی</dt>
            <dd>{{ $preorder->name }}</dd>

            <dt>محصول</dt>
            <dd>{{ $preorder->landingPage?->idea?->name }}</dd>

            <dt>مبلغ</dt>
            <dd>{{ \App\Support\Money::formatRialAsToman($payment?->amount_rial ?? $preorder->amount_rial) }}</dd>

            <dt>کد پیگیری</dt>
            <dd class="ltr"><strong>{{ $payment?->ref_id }}</strong></dd>

            <dt>زمان</dt>
            <dd class="ltr">{{ \App\Support\Format::datetime($payment?->verified_at) }}</dd>

            @if ($preorder->refundPolicy)
                <dt>شرایط بازپرداخت</dt>
                <dd>{{ $preorder->refundPolicy->summary() }}</dd>
            @endif
        </dl>

        <p class="muted">
            لطفاً کد پیگیری را نگه دارید. وضعیت سفارش، رسید و درخواست بازپرداخت را هر زمان با همین شماره موبایل از «پیگیری سفارش» ببینید.
        </p>

        <a class="button" href="{{ route('account.login') }}">پیگیری سفارش و درخواست بازپرداخت</a>

    @elseif ($payment?->status === \App\Models\Payment::STATUS_PENDING && $payment->authority)
        <h1>در حال بررسی پرداخت</h1>

        <div class="status status-pending" role="status">
            نتیجه پرداخت هنوز از درگاه تأیید نشده است.
            اگر مبلغ از حساب شما کسر شده، لطفاً دوباره پرداخت نکنید؛
            پرداخت شما بررسی و نتیجه اعلام می‌شود.
        </div>

        <a class="button" href="{{ route('preorders.receipt', $preorder->public_token) }}">بررسی دوباره وضعیت</a>

    @else
        <h1>پرداخت انجام نشد</h1>

        <div class="status status-failed" role="alert">
            @if ($payment?->status === \App\Models\Payment::STATUS_CANCELLED)
                پرداخت لغو شد و مبلغی از حساب شما برداشت نشده است.
            @else
                پرداخت توسط درگاه تأیید نشد. اگر مبلغی از حساب شما کسر شده،
                معمولاً به‌طور خودکار بازمی‌گردد؛ در غیر این صورت با پشتیبانی تماس بگیرید.
            @endif
        </div>

        <a class="button" href="{{ route('preorders.checkout', $preorder->public_token) }}">تلاش دوباره برای پرداخت</a>
    @endif
</div>

<x-site-footer />
</body>
</html>

@extends('layouts.public')

@section('title', 'حساب من')

@section('content')
    <h1>سلام{{ $contact->name ? ' ' . $contact->name : '' }}</h1>

    <section class="card">
        <h2>سفارش‌های پیش‌خرید</h2>

        @if ($preorders->isEmpty())
            <p class="muted" style="margin: 0">هنوز سفارشی ثبت نکرده‌اید.</p>
        @else
            <ul class="list">
                @foreach ($preorders as $preorder)
                    <li>
                        <div class="row">
                            <strong>{{ $preorder->landingPage?->idea?->name }}</strong>
                            @if ($preorder->isPaid())
                                <span class="badge badge-success">پرداخت‌شده</span>
                            @else
                                <span class="badge badge-warning">پرداخت‌نشده</span>
                            @endif
                        </div>

                        <div class="row small muted">
                            <span>
                                {{ \App\Support\Money::formatRialAsToman($preorder->amount_rial) }}
                                — <span class="ltr">{{ \App\Support\Format::datetime($preorder->created_at, 'Y-m-d') }}</span>
                                @if ($preorder->refundPolicy)
                                    — {{ $preorder->refundPolicy->summary() }}
                                @endif
                            </span>

                            @if ($preorder->public_token && $preorder->amount_rial)
                                @if ($preorder->isPaid())
                                    <a href="{{ route('preorders.receipt', $preorder->public_token) }}">مشاهده رسید</a>
                                @else
                                    <a href="{{ route('preorders.checkout', $preorder->public_token) }}">تکمیل پرداخت</a>
                                @endif
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <h2>ایده‌های مورد علاقه من</h2>

        @if ($ideas->isEmpty())
            <p class="muted" style="margin: 0">هنوز به ایده‌ای علاقه اعلام نکرده‌اید.</p>
        @else
            <ul class="list">
                @foreach ($ideas as $idea)
                    <li class="row">
                        <span>{{ $idea->name }}</span>
                        <span class="badge">{{ $idea->statusEnum()->label() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card">
        <h2>پیامک‌های معرفی و پیشنهاد</h2>

        <p class="muted small">
            پیام‌های مربوط به سفارش و کد ورود همیشه ارسال می‌شوند. پیامک‌های معرفی محصولات و پیشنهادهای جدید فقط با رضایت شما ارسال می‌شود.
        </p>

        <form method="POST" action="{{ route('account.consent') }}" class="row">
            @csrf
            @method('PUT')

            @if ($contact->marketing_sms_consent)
                <span>
                    <span class="badge badge-success">فعال</span>
                    <span class="small muted">از <span class="ltr">{{ \App\Support\Format::datetime($contact->marketing_consent_at, 'Y-m-d') }}</span></span>
                </span>
                <input type="hidden" name="marketing_sms_consent" value="0">
                <button type="submit" class="button-secondary">لغو دریافت</button>
            @else
                <span class="badge">غیرفعال</span>
                <input type="hidden" name="marketing_sms_consent" value="1">
                <button type="submit">فعال‌سازی</button>
            @endif
        </form>
    </section>

    <section class="card">
        <h2>اطلاعات من</h2>

        <form method="POST" action="{{ route('account.profile') }}">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label>شماره موبایل</label>
                <div class="ltr" style="text-align: right">{{ $contact->mobile }}</div>
            </div>

            <div class="form-group">
                <label for="name">نام</label>
                <input id="name" type="text" name="name" maxlength="120" value="{{ old('name', $contact->name) }}" autocomplete="name"
                    @error('name') aria-invalid="true" @enderror>
                @error('name')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label for="email">ایمیل (اختیاری)</label>
                <input id="email" type="email" name="email" maxlength="255" value="{{ old('email', $contact->email) }}" autocomplete="email" class="ltr-input"
                    @error('email') aria-invalid="true" @enderror>
                @error('email')<div class="field-error">{{ $message }}</div>@enderror
            </div>

            <button type="submit">ذخیره</button>
        </form>
    </section>
@endsection

@extends('layouts.public')

@section('title', 'پیگیری سفارش و ورود')

@section('content')
    <div class="card" style="max-width: 440px; margin-inline: auto">
        <h1>پیگیری سفارش و ورود</h1>
        <p class="muted">
            با همان شماره موبایلی که پیش‌خرید یا ثبت‌نام کرده‌اید وارد شوید. کد ورود برایتان پیامک می‌شود.
        </p>

        <ul class="small" style="padding-inline-start: 20px">
            <li>دیدن وضعیت پیش‌خرید و رسید پرداخت</li>
            <li>ثبت و پیگیری درخواست بازپرداخت</li>
            <li>مدیریت پیامک‌های اطلاع‌رسانی</li>
        </ul>

        <form method="POST" action="{{ route('account.login.send') }}">
            @csrf

            <div class="form-group">
                <label for="mobile">شماره موبایل</label>
                <input
                    id="mobile" type="tel" name="mobile" value="{{ old('mobile') }}"
                    inputmode="tel" autocomplete="tel" maxlength="30" placeholder="09123456789"
                    class="ltr-input" required autofocus
                    @error('mobile') aria-invalid="true" aria-describedby="mobile-error" @enderror
                >
                @error('mobile')
                    <div class="field-error" id="mobile-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit">دریافت کد ورود</button>
        </form>

        <p class="muted small" style="margin-bottom: 0">
            اگر قبلاً در یکی از صفحات ما ثبت‌نام یا پیش‌خرید کرده‌اید، با همان شماره وارد شوید تا سوابق خود را ببینید.
        </p>
    </div>
@endsection

@extends('layouts.public')

@section('title', 'ورود به حساب')

@section('content')
    <div class="card" style="max-width: 440px; margin-inline: auto">
        <h1>ورود یا ثبت‌نام</h1>
        <p class="muted">شماره موبایل خود را وارد کنید تا کد ورود برایتان پیامک شود.</p>

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

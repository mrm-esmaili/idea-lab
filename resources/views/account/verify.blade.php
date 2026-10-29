@extends('layouts.public')

@section('title', 'کد ورود')

@section('content')
    <div class="card" style="max-width: 440px; margin-inline: auto">
        <h1>کد ورود</h1>
        <p class="muted">
            کد ارسال‌شده به <span class="ltr">{{ $mobile }}</span> را وارد کنید.
        </p>

        <form method="POST" action="{{ route('account.verify.submit') }}">
            @csrf

            <div class="form-group">
                <label for="code">کد</label>
                <input
                    id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                    maxlength="10" class="code-input" required autofocus
                    @error('code') aria-invalid="true" aria-describedby="code-error" @enderror
                >
                @error('code')
                    <div class="field-error" id="code-error">{{ $message }}</div>
                @enderror
            </div>

            <button type="submit">ورود</button>
        </form>

        <div class="row small" style="margin-top: 16px">
            <form method="POST" action="{{ route('account.login.send') }}">
                @csrf
                <input type="hidden" name="mobile" value="{{ $mobile }}">
                <button type="submit" class="button-link" id="resend" @disabled($resendWait > 0) data-wait="{{ $resendWait }}">
                    ارسال دوباره کد<span id="resend-timer">@if ($resendWait > 0) ({{ $resendWait }})@endif</span>
                </button>
            </form>

            <a href="{{ route('account.login') }}">تغییر شماره</a>
        </div>
    </div>
@endsection

@push('scripts')
<script>
(function () {
    const button = document.getElementById('resend');
    const timer = document.getElementById('resend-timer');
    let wait = parseInt(button.dataset.wait, 10) || 0;

    if (wait <= 0) {
        return;
    }

    const tick = setInterval(function () {
        wait--;
        timer.textContent = wait > 0 ? ' (' + wait + ')' : '';

        if (wait <= 0) {
            button.disabled = false;
            clearInterval(tick);
        }
    }, 1000);
})();
</script>
@endpush

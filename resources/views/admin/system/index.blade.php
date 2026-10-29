@extends('layouts.admin')

@section('title', 'وضعیت سیستم')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">وضعیت سیستم</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge text-bg-danger fs-6">{{ number_format($counts['error'] ?? 0) }} مشکل</span>
        <span class="badge text-bg-warning fs-6">{{ number_format($counts['warn'] ?? 0) }} هشدار</span>
        <span class="badge text-bg-success fs-6">{{ number_format($counts['ok'] ?? 0) }} درست</span>
    </div>

    <p class="small text-body-secondary">
        مقدار کلیدها و رمزها هرگز نمایش داده نمی‌شود. همه تنظیمات از فایل <span class="ltr">.env</span> سرور خوانده می‌شود
        (cPanel ← File Manager)؛ اگر کش پیکربندی فعال است پس از تغییر <span class="ltr">.env</span> آن را دوباره بسازید.
    </p>

    @foreach ($groups as $group => $checks)
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">{{ $group }}</h2></div>
            <ul class="list-group list-group-flush">
                @foreach ($checks as $check)
                    <li class="list-group-item d-flex gap-2 align-items-start">
                        @php($icon = ['ok' => 'bi-check-circle-fill text-success', 'warn' => 'bi-exclamation-triangle-fill text-warning', 'error' => 'bi-x-circle-fill text-danger'][$check['status']])
                        <i class="bi {{ $icon }} mt-1" aria-label="{{ ['ok' => 'درست', 'warn' => 'هشدار', 'error' => 'مشکل'][$check['status']] }}"></i>
                        <div class="flex-grow-1">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <span class="fw-semibold">{{ $check['label'] }}</span>
                                <span class="small text-body-secondary"><bdi>{{ $check['detail'] }}</bdi></span>
                            </div>
                            @if ($check['fix'])
                                <div class="small mt-1">{{ $check['fix'] }}</div>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endforeach
@endsection

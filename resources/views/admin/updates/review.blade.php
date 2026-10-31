@extends('layouts.admin')

@section('title', 'بررسی بسته ' . \App\Support\Format::ltr($bundle->version()))

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.updates.index') }}">به‌روزرسانی</a></li>
    <li class="breadcrumb-item active" aria-current="page"><bdi>{{ $bundle->version() }}</bdi></li>
@endsection

@section('content')
    @php($blocking = collect($checks)->where('blocking', true)->isNotEmpty())

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">بسته</h2></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-sm-4">نسخه</dt><dd class="col-sm-8"><bdi class="ltr">{{ $current }} → {{ $bundle->version() }}</bdi></dd>
                        <dt class="col-sm-4">فایل</dt><dd class="col-sm-8"><bdi>{{ $name }}</bdi></dd>
                        <dt class="col-sm-4">زمان ساخت</dt><dd class="col-sm-8">{{ $bundle->builtAt() ? \App\Support\Format::datetime(\Illuminate\Support\Carbon::parse($bundle->builtAt())) : '—' }}</dd>
                        @if ($bundle->commit())
                            <dt class="col-sm-4">commit</dt><dd class="col-sm-8"><bdi>{{ \Illuminate\Support\Str::limit($bundle->commit(), 12, '') }}</bdi></dd>
                        @endif
                        <dt class="col-sm-4">کتابخانه‌ها (vendor)</dt><dd class="col-sm-8">{{ $bundle->vendorIncluded() ? 'دارد (بسته کامل)' : 'ندارد (بسته سبک)' }}</dd>
                        <dt class="col-sm-4">SHA-256</dt><dd class="col-sm-8 text-break"><code dir="ltr">{{ $bundle->sha256() }}</code></dd>
                    </dl>
                    <div class="form-text">برای اطمینان از اصل بودن بسته، SHA-256 را با عدد چاپ‌شده در خلاصه اجرای GitHub Actions مقایسه کنید.</div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">تغییرات</h2></div>
                <div class="card-body small">
                    <ul class="mb-2">
                        <li>{{ number_format(count($plan['changed'])) }} فایل تغییر می‌کند، {{ number_format(count($plan['added'])) }} فایل اضافه و {{ number_format(count($plan['removed'])) }} فایل حذف می‌شود ({{ number_format($plan['unchanged']) }} فایل بدون تغییر).</li>
                        <li>migrationهای جدید: {{ count($plan['new_migrations']) ? '' : 'ندارد' }}
                            @if ($plan['new_migrations'])
                                <ul class="list-unstyled">
                                    @foreach ($plan['new_migrations'] as $migration)
                                        <li><code dir="ltr">{{ $migration }}</code></li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                        @if ($plan['modified_kept'])
                            <li class="text-warning-emphasis">{{ count($plan['modified_kept']) }} فایل حذف‌شده در نسخه جدید روی سرور دستی تغییر کرده و نگه داشته می‌شود.</li>
                        @endif
                    </ul>
                    @if ($plan['changed'])
                        <details>
                            <summary>فهرست فایل‌های تغییرکننده</summary>
                            <ul class="list-unstyled mb-0 mt-2">
                                @foreach (array_slice($plan['changed'], 0, 200) as $file)
                                    <li><code dir="ltr">{{ $file }}</code></li>
                                @endforeach
                                @if (count($plan['changed']) > 200)
                                    <li>…</li>
                                @endif
                            </ul>
                        </details>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">پیش‌نیازها</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach ($checks as $check)
                        <li class="list-group-item d-flex gap-2 small">
                            <i class="bi {{ $check['ok'] ? 'bi-check-circle-fill text-success' : ($check['blocking'] ? 'bi-x-circle-fill text-danger' : 'bi-exclamation-triangle-fill text-warning') }} mt-1"></i>
                            <div><div class="fw-semibold">{{ $check['label'] }}</div><bdi>{{ $check['detail'] }}</bdi></div>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if (version_compare($bundle->version(), $current) === 0 && ! $reinstall)
                <a href="{{ route('admin.updates.review', [$name, 'reinstall' => 1]) }}" class="btn btn-outline-secondary btn-sm mb-3">نصب دوباره همین نسخه</a>
            @endif

            <div class="card">
                <div class="card-body">
                    @if ($blocking)
                        <p class="text-danger small mb-0">تا رفع موارد قرمز، نصب ممکن نیست.</p>
                    @else
                        <form method="POST" action="{{ route('admin.updates.apply', $name) }}" data-confirm="نسخه {{ $bundle->version() }} نصب شود؟&#10;سایت چند لحظه در حالت تعمیر خواهد بود." data-confirm-variant="warning">
                            @csrf
                            @if ($reinstall)
                                <input type="hidden" name="reinstall" value="1">
                            @endif
                            <div class="mb-3">
                                <label for="password" class="form-label">رمز عبور شما (برای تأیید)</label>
                                <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" autocomplete="current-password" required>
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-warning w-100"><i class="bi bi-cloud-arrow-up"></i> نصب نسخه <bdi>{{ $bundle->version() }}</bdi></button>
                            <div class="form-text">پیش از نصب از پایگاه داده پشتیبان گرفته می‌شود. این صفحه را تا پایان نبندید.</div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

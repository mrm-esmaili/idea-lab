@extends('layouts.admin')

@section('title', 'به‌روزرسانی')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">به‌روزرسانی</li>
@endsection

@section('content')
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body">
                    <div class="small text-body-secondary">نسخه نصب‌شده</div>
                    <div class="fs-3 fw-bold ltr text-end"><bdi>{{ $version }}</bdi></div>
                </div>
            </div>
        </div>
        <div class="col-md-8">
            @if ($pending)
                <div class="alert alert-warning h-100 mb-0">
                    <strong>به‌روزرسانی نیمه‌کاره:</strong> #{{ $pending->id }} ({{ $pending->statusLabel() }}).
                    <a href="{{ route('admin.updates.show', $pending) }}">تکمیل یا بازگرداندن</a>
                </div>
            @elseif (! $enabled)
                <div class="alert alert-secondary h-100 mb-0">
                    به‌روزرسانی از پنل با <span class="ltr">APP_UPDATES_ENABLED=false</span> در <span class="ltr">.env</span> خاموش است.
                    از روش Cron یا File Manager در <span class="ltr">DEPLOYMENT.md</span> استفاده کنید.
                </div>
            @else
                <div class="card h-100">
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.updates.upload') }}" enctype="multipart/form-data" class="d-flex flex-wrap gap-2 align-items-end">
                            @csrf
                            <div class="flex-grow-1">
                                <label for="package" class="form-label">بارگذاری بسته به‌روزرسانی (zip)</label>
                                <input type="file" name="package" id="package" accept=".zip" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary"><i class="bi bi-upload"></i> بارگذاری و بررسی</button>
                        </form>
                        <div class="form-text">
                            حداکثر حجم در این هاست: {{ $uploadLimit }} مگابایت. اگر بسته بزرگ‌تر است، آن را با File Manager در پوشه
                            <span class="ltr">{{ $directory }}</span> قرار دهید تا در فهرست زیر دیده شود.
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if ($enabled)
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">بسته‌های آماده نصب</h2></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>فایل</th><th>زمان</th><th>حجم</th><th></th></tr></thead>
                        <tbody>
                            @forelse ($packages as $package)
                                <tr>
                                    <td class="small"><bdi>{{ $package['name'] }}</bdi></td>
                                    <td class="small text-nowrap">{{ \App\Support\Format::datetime($package['modified_at']) }}</td>
                                    <td class="small text-nowrap">{{ number_format($package['size'] / 1048576, 1) }} MB</td>
                                    <td class="text-end text-nowrap">
                                        <a href="{{ route('admin.updates.review', $package['name']) }}" class="btn btn-sm btn-primary">بررسی و نصب</a>
                                        <form method="POST" action="{{ route('admin.updates.packages.destroy', $package['name']) }}" class="d-inline" data-confirm="بسته {{ $package['name'] }} حذف شود؟">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="حذف"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <x-admin.empty-row colspan="4" icon="bi-box-seam">بسته‌ای بارگذاری نشده است.</x-admin.empty-row>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title h6 mb-0">تاریخچه</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>#</th><th>نسخه</th><th>روش</th><th>وضعیت</th><th>کاربر</th><th>زمان</th></tr></thead>
                    <tbody>
                        @forelse ($updates as $update)
                            <tr>
                                <td><a href="{{ route('admin.updates.show', $update) }}">{{ $update->id }}</a></td>
                                <td class="small"><bdi class="ltr">{{ $update->from_version ?? '—' }} → {{ $update->to_version }}</bdi></td>
                                <td class="small">{{ \App\Models\SystemUpdate::METHODS[$update->method] ?? $update->method }}</td>
                                <td><span class="badge text-bg-{{ $update->statusColor() }}">{{ $update->statusLabel() }}</span></td>
                                <td class="small">{{ $update->user?->name ?? '—' }}</td>
                                <td class="small text-nowrap">{{ \App\Support\Format::datetime($update->started_at) }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="6" icon="bi-clock-history">هنوز به‌روزرسانی‌ای ثبت نشده است.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $updates->links() }}

    <div class="card">
        <div class="card-header"><h2 class="card-title h6 mb-0">روند به‌روزرسانی</h2></div>
        <div class="card-body small">
            <ol class="mb-0">
                <li>بسته را از GitHub ← Actions ← Build release package بسازید. فایل دانلودی یک zip است که بسته اصلی (<span class="ltr">idea-lab-X.Y.Z.zip</span>) داخل آن است؛ اول آن را روی رایانه باز کنید.</li>
                <li>بسته را اینجا بارگذاری کنید؛ سالم بودن همه فایل‌ها با چک‌سام بررسی و تغییرات پیش از نصب نمایش داده می‌شود.</li>
                <li>با تأیید رمز: پشتیبان پایگاه داده، نسخه پشتیبان فایل‌های قدیمی، حالت تعمیر (چند ثانیه تا چند دقیقه)، جایگزینی فایل‌ها، migration و ساخت کش. به <span class="ltr">.env</span> و فایل‌های آپلودی دست زده نمی‌شود.</li>
                <li>اگر هر مرحله شکست بخورد، فایل‌های قبلی خودکار برمی‌گردند. پس از نصب هم تا پیش از به‌روزرسانی بعدی می‌توانید کد را به نسخه قبل برگردانید.</li>
            </ol>
        </div>
    </div>
@endsection

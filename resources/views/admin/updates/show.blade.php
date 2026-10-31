@extends('layouts.admin')

@section('title', 'به‌روزرسانی #' . $update->id)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.updates.index') }}">به‌روزرسانی</a></li>
    <li class="breadcrumb-item active" aria-current="page">#{{ $update->id }}</li>
@endsection

@section('content')
    @if ($update->status === \App\Models\SystemUpdate::FILES_APPLIED)
        <div class="card border-warning mb-3">
            <div class="card-body">
                <h2 class="h5">فایل‌ها جایگزین شد؛ مرحله آخر: migration و ساخت کش</h2>
                <p class="small mb-3">سایت تا پایان این مرحله در حالت تعمیر است.</p>
                <form method="POST" action="{{ route('admin.updates.finish', $update) }}" id="finish-form">
                    @csrf
                    <button type="submit" class="btn btn-warning"><span class="spinner-border spinner-border-sm d-none" aria-hidden="true"></span> تکمیل به‌روزرسانی</button>
                </form>
            </div>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-sm-3">نسخه</dt><dd class="col-sm-9"><bdi class="ltr">{{ $update->from_version ?? '—' }} → {{ $update->to_version }}</bdi></dd>
                <dt class="col-sm-3">وضعیت</dt><dd class="col-sm-9"><span class="badge text-bg-{{ $update->statusColor() }}">{{ $update->statusLabel() }}</span></dd>
                <dt class="col-sm-3">روش</dt><dd class="col-sm-9">{{ \App\Models\SystemUpdate::METHODS[$update->method] ?? $update->method }}{{ $update->user ? ' — ' . $update->user->name : '' }}</dd>
                @if ($update->package)
                    <dt class="col-sm-3">بسته</dt><dd class="col-sm-9"><bdi>{{ $update->package }}</bdi></dd>
                @endif
                <dt class="col-sm-3">شروع / پایان</dt><dd class="col-sm-9">{{ \App\Support\Format::datetime($update->started_at) }} / {{ \App\Support\Format::datetime($update->finished_at) }}</dd>
                <dt class="col-sm-3">پشتیبان پایگاه داده</dt><dd class="col-sm-9"><bdi>{{ $update->backup_file ?? '—' }}</bdi></dd>
                @php($summary = $update->summary ?? [])
                @if (isset($summary['changed']))
                    <dt class="col-sm-3">فایل‌ها</dt><dd class="col-sm-9">{{ number_format($summary['changed']) }} تغییر، {{ number_format($summary['added']) }} جدید، {{ number_format($summary['removed']) }} حذف</dd>
                @endif
                @if (! empty($summary['new_migrations']))
                    <dt class="col-sm-3">migrationهای جدید</dt><dd class="col-sm-9">@foreach ($summary['new_migrations'] as $migration)<code class="d-block" dir="ltr">{{ $migration }}</code>@endforeach</dd>
                @endif
                @if (! empty($summary['package_sha256']))
                    <dt class="col-sm-3">SHA-256</dt><dd class="col-sm-9 text-break"><code dir="ltr">{{ $summary['package_sha256'] }}</code></dd>
                @endif
            </dl>
        </div>
    </div>

    @if ($update->error)
        <div class="alert alert-danger small"><strong>خطا:</strong> <bdi>{{ $update->error }}</bdi></div>
    @endif

    @if (! empty($summary['migrate_output']))
        <details class="mb-3">
            <summary class="small">خروجی migration</summary>
            <pre class="ltr text-start small bg-body-tertiary p-2 mt-2 mb-0">{{ $summary['migrate_output'] }}</pre>
        </details>
    @endif

    @if ($canRollback)
        <div class="card border-danger-subtle">
            <div class="card-body">
                <h2 class="h6">بازگرداندن کد به نسخه <bdi>{{ $update->from_version }}</bdi></h2>
                <p class="small">فایل‌های جایگزین‌شده برمی‌گردند و فایل‌های جدید حذف می‌شوند. پایگاه داده تغییر نمی‌کند (migrationها افزایشی‌اند و نسخه قبل با آن کار می‌کند).</p>
                <form method="POST" action="{{ route('admin.updates.rollback', $update) }}" class="d-flex flex-wrap gap-2 align-items-end" data-confirm="کد به نسخه {{ $update->from_version }} برگردانده شود؟" data-confirm-variant="danger">
                    @csrf
                    <div>
                        <label for="rollback-password" class="form-label small">رمز عبور شما</label>
                        <input type="password" name="password" id="rollback-password" class="form-control form-control-sm" autocomplete="current-password" required>
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-danger">بازگرداندن</button>
                </form>
            </div>
        </div>
    @endif
@endsection

@if ($update->status === \App\Models\SystemUpdate::FILES_APPLIED && session('auto_finish'))
    @push('scripts')
        <script>
            // مرحله دوم در درخواست جدید و با کد تازه اجرا می‌شود
            document.addEventListener('DOMContentLoaded', function () {
                const form = document.getElementById('finish-form');
                form.querySelector('.spinner-border').classList.remove('d-none');
                form.querySelector('button').disabled = true;
                form.submit();
            });
        </script>
    @endpush
@endif

@extends('layouts.admin')

@section('title', 'گزارش پیامک و هزینه')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">پیامک و هزینه</li>
@endsection

@section('content')
    @include('admin.reports._range')

    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-3">
        @foreach ([
            ['کل پیامک‌ها', number_format($report['total']), 'bi-chat-dots', 'primary'],
            ['پذیرفته‌شده توسط ارائه‌دهنده', number_format($report['sent']), 'bi-check2-all', 'success'],
            ['ناموفق', number_format($report['failed']) . ' (' . number_format($report['failure_rate'], 1) . '%)', 'bi-exclamation-triangle', 'danger'],
            ['هزینه گزارش‌شده', number_format($report['cost'], 0), 'bi-coin', 'warning'],
        ] as [$label, $value, $icon, $color])
            <div class="col">
                <div class="info-box h-100">
                    <span class="info-box-icon text-bg-{{ $color }} shadow-sm"><i class="bi {{ $icon }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $label }}</span>
                        <span class="info-box-number">{{ $value }}</span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">به تفکیک نوع</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr><th>نوع</th>@foreach (['sent', 'failed', 'queued', 'skipped', 'cancelled'] as $status)<th>{{ config("statuses.sms_message.{$status}") }}</th>@endforeach<th>هزینه</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($report['by_category'] as $category => $row)
                                    <tr>
                                        <td>{{ config("statuses.sms_category.{$category}", $category) }}</td>
                                        @foreach (['sent', 'failed', 'queued', 'skipped', 'cancelled'] as $status)
                                            <td>{{ number_format($row['statuses'][$status] ?? 0) }}</td>
                                        @endforeach
                                        <td>{{ number_format($row['cost']) }}</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="7">در این بازه پیامکی نیست.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">خطاهای پرتکرار</h2></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($report['errors'] as $error)
                        <li class="list-group-item d-flex justify-content-between gap-2"><span>{{ $error->error }}</span><span class="badge text-bg-danger">{{ number_format($error->total) }}</span></li>
                    @empty
                        <li class="list-group-item text-body-secondary">خطایی ثبت نشده است.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">به تفکیک قالب</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>قالب</th><th>کل</th><th>ارسال شد</th><th>ناموفق</th><th>هزینه</th></tr></thead>
                            <tbody>
                                @forelse ($report['by_template'] as $row)
                                    <tr>
                                        <td>{{ $templateNames[$row->template_key] ?? $row->template_key }}</td>
                                        <td>{{ number_format($row->total) }}</td>
                                        <td>{{ number_format($row->sent) }}</td>
                                        <td>{{ number_format($row->failed) }}</td>
                                        <td>{{ number_format((float) $row->cost) }}</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="5">پیامک قالب‌داری نیست.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">ارسال‌های گروهی تأییدشده در بازه</h2></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($report['broadcasts'] as $broadcast)
                        <li class="list-group-item d-flex justify-content-between gap-2">
                            <a href="{{ route('admin.sms.broadcasts.show', $broadcast) }}">{{ $broadcast->title }}</a>
                            <span>{{ number_format($broadcast->recipients_count) }} گیرنده — {{ \App\Support\Format::date($broadcast->confirmed_at) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary">ارسال گروهی نبوده است.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>

    <p class="small text-body-secondary">
        «ارسال شد» یعنی پذیرش پیام توسط SMS.ir؛ گزارش تحویل به گوشی دریافت نمی‌شود. هزینه همان مقداری است که SMS.ir در پاسخ ارسال اعلام کرده
        (واحد ارائه‌دهنده) و برای پیامک ناموفق یا درایور آزمایشی خالی است.
    </p>
@endsection

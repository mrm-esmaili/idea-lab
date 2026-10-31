@extends('layouts.admin')

@section('title', 'قیف تبدیل')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">قیف تبدیل</li>
@endsection

@section('content')
    @include('admin.reports._range')

    @php($top = max(1, $report['steps'][0]['value']))
    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title h6 mb-0">مراحل قیف</h2></div>
        <div class="card-body">
            @foreach ($report['steps'] as $step)
                <div class="mb-3">
                    <div class="d-flex flex-wrap justify-content-between gap-2 small mb-1">
                        <span><strong>{{ $step['label'] }}</strong> <span class="text-body-secondary">— {{ $step['definition'] }}</span></span>
                        <span>
                            <strong>{{ number_format($step['value']) }}</strong>
                            @if ($step['of_previous'] !== null)
                                <span class="text-body-secondary">({{ number_format($step['of_visitors'], 2) }}% بازدیدکنندگان، {{ number_format($step['of_previous'], 2) }}% مرحله قبل)</span>
                            @endif
                        </span>
                    </div>
                    <div class="progress" role="progressbar" aria-label="{{ $step['label'] }}" aria-valuenow="{{ $step['value'] }}" aria-valuemin="0" aria-valuemax="{{ $top }}" style="height: 1.25rem">
                        <div class="progress-bar" style="width: {{ min(100, $step['value'] / $top * 100) }}%"></div>
                    </div>
                </div>
            @endforeach
            <p class="small text-body-secondary mb-0">
                بازدید صفحه (بدون یکتاسازی): {{ number_format($report['page_views']) }} —
                کلیک دکمه‌های اقدام: {{ number_format($report['cta_clicks']) }} (مرحله قیف نیست؛ فرم بدون کلیک هم ثبت می‌شود) —
                درخواست «خبرم کن» (بدون پیش‌پرداخت، بخشی از سرنخ‌ها): {{ number_format($report['totals']['notify_requests']) }}
                ({{ number_format(\App\Services\FunnelStats::rate($report['totals']['notify_requests'], $report['totals']['unique_visitors']), 2) }}% بازدیدکنندگان؛ خروجی موازی پیش‌خرید، نه مرحله‌ای از آن) —
                وجه پیش‌پرداخت تأییدشده: {{ \App\Support\Money::formatRialAsToman($report['totals']['paid_rial']) }}.
                هر مرحله رویدادهای ثبت‌شده در همان بازه است؛ نفرات یک مرحله لزوماً زیرمجموعه مرحله قبل نیستند (مثلاً پرداخت امروز برای سفارش ماه قبل).
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title h6 mb-0">به تفکیک ایده</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr><th>ایده</th><th>بازدیدکننده</th><th>سرنخ</th><th>نرخ سرنخ</th><th>پیش‌خرید</th><th>«خبرم کن»</th><th>پیش‌پرداخت</th><th>نرخ پرداخت</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($report['per_idea'] as $row)
                            <tr>
                                <td><a href="{{ route('ideas.show', $row['idea']) }}">{{ $row['idea']->name }}</a></td>
                                <td>{{ number_format($row['unique_visitors']) }}</td>
                                <td>{{ number_format($row['leads']) }}</td>
                                <td>{{ number_format($row['lead_rate'], 2) }}%</td>
                                <td>{{ number_format($row['preorders']) }}</td>
                                <td>{{ number_format($row['notify_requests']) }} <span class="small text-body-secondary">({{ number_format(\App\Services\FunnelStats::rate($row['notify_requests'], $row['unique_visitors']), 2) }}%)</span></td>
                                <td>{{ number_format($row['paid_count']) }}</td>
                                <td>{{ number_format($row['paid_rate'], 2) }}%</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="8">داده‌ای نیست.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

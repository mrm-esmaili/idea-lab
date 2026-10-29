@extends('layouts.admin')

@section('title', 'عملکرد منابع جذب')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">منابع جذب</li>
@endsection

@section('content')
    @include('admin.reports._range')

    <div class="card mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>منبع</th>
                            <th>بازدیدکننده</th>
                            <th>سرنخ</th>
                            <th>نرخ سرنخ</th>
                            <th>پیش‌خرید</th>
                            <th>پیش‌پرداخت</th>
                            <th>نرخ پرداخت</th>
                            <th>مبلغ دریافتی</th>
                            <th>هزینه کمپین‌ها</th>
                            <th>هزینه هر سرنخ</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>{!! $row['source'] ? '<span class="ltr">' . e($row['source']) . '</span>' : '<span class="text-body-secondary">مستقیم / بدون UTM</span>' !!}</td>
                                <td>{{ number_format($row['unique_visitors']) }}</td>
                                <td>{{ number_format($row['leads']) }}</td>
                                <td>{{ number_format($row['lead_rate'], 2) }}%</td>
                                <td>{{ number_format($row['preorders']) }}</td>
                                <td>{{ number_format($row['paid_count']) }}</td>
                                <td>{{ number_format($row['paid_rate'], 2) }}%</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($row['paid_rial']) }}</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($row['spent_rial']) }}</td>
                                <td class="text-nowrap">{{ $row['cost_per_lead'] !== null ? \App\Support\Money::formatRialAsToman($row['cost_per_lead']) : '—' }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="10" icon="bi-signpost-split">در این بازه داده‌ای نیست.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="small text-body-secondary">
        منبع هر اقدام مقدار <span class="ltr">utm_source</span> ثبت‌شده روی همان اقدام است (بدون حساسیت به بزرگی حروف).
        هزینه هر منبع جمع هزینه‌های ثبت‌شده برای کمپین‌هایی است که همین منبع را دارند؛ هزینه بدون کمپین در این گزارش نیست.
        برای مقایسه دقیق کمپین‌ها از «مقایسه کمپین‌ها» استفاده کنید.
    </p>
@endsection

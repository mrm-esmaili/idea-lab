@extends('layouts.admin')

@section('title', 'اقتصاد ایده‌ها')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">اقتصاد ایده‌ها</li>
@endsection

@section('content')
    @include('admin.reports._range')

    <div class="card mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>ایده</th>
                            <th>هزینه کل</th>
                            <th>هزینه تبلیغات</th>
                            <th>دریافتی</th>
                            <th>بازپرداخت</th>
                            <th>خالص</th>
                            <th>درآمد شناسایی‌شده</th>
                            <th>سرنخ</th>
                            <th>مشتری</th>
                            <th>هزینه هر سرنخ</th>
                            <th>CAC</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    <a href="{{ route('ideas.show', $row['idea']) }}">{{ $row['idea']->name }}</a>
                                    <div><x-admin.status-badge group="idea" :value="$row['idea']->status" /></div>
                                </td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($row['expenses']) }}</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($row['ad_spend']) }}</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($row['received']) }}</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($row['refunded']) }}</td>
                                <td class="text-nowrap fw-semibold">{{ \App\Support\Money::formatRialAsToman($row['net']) }}</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($row['recognized']) }}</td>
                                <td>{{ number_format($row['leads']) }}</td>
                                <td>{{ number_format($row['customers']) }}</td>
                                <td class="text-nowrap">{{ $row['cost_per_lead'] !== null ? \App\Support\Money::formatRialAsToman($row['cost_per_lead']) : '—' }}</td>
                                <td class="text-nowrap">
                                    @if ($row['cac'] !== null)
                                        {{ \App\Support\Money::formatRialAsToman($row['cac']) }}
                                    @elseif ($row['cac_insufficient'])
                                        <span class="small text-body-secondary">داده ناکافی</span>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="11" icon="bi-calculator">در این بازه داده‌ای نیست.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title h6 mb-0">تعریف‌ها</h2></div>
        <div class="card-body small">
            <ul class="mb-0">
                <li><strong>هزینه کل / تبلیغات:</strong> هزینه‌های ثبت‌شده با تاریخ وقوع در بازه؛ تبلیغات فقط دسته «تبلیغات».</li>
                <li><strong>دریافتی، بازپرداخت، خالص، درآمد شناسایی‌شده:</strong> همان تعاریف گزارش مالی (مبنای نقدی)؛ درآمد فقط برای ایده عرضه‌شده و بقیه پیش‌دریافت است.</li>
                <li><strong>مشتری:</strong> سفارش‌هایی که پرداختشان در بازه تأیید شده.</li>
                <li><strong>هزینه هر سرنخ:</strong> هزینه تبلیغات ÷ سرنخ.</li>
                <li><strong>CAC:</strong> هزینه تبلیغات ÷ مشتری؛ فقط وقتی دست‌کم {{ $cacMin }} مشتری در بازه باشد، در غیر این صورت «داده ناکافی».</li>
            </ul>
        </div>
    </div>
@endsection

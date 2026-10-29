@extends('layouts.admin')

@section('title', 'گزارش باشگاه مشتریان')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">باشگاه مشتریان</li>
@endsection

@section('content')
    @include('admin.reports._range')

    @php($all = $report['all'])
    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-3">
        @foreach ([
            ['کل مخاطبان', number_format($all['total']), 'bi-people', 'primary', null],
            ['دارای رضایت پیامک تبلیغاتی', number_format($all['consent']), 'bi-check2-circle', 'success', $all['total'] ? number_format($all['consent'] / $all['total'] * 100, 1) . '% مخاطبان' : null],
            ['لغو رضایت', number_format($all['opted_out']), 'bi-x-circle', 'danger', null],
            ['مخاطب جدید در بازه', number_format($report['new']['total']), 'bi-person-plus', 'info', number_format($report['new']['consent']) . ' با رضایت'],
        ] as [$label, $value, $icon, $color, $hint])
            <div class="col">
                <div class="info-box h-100">
                    <span class="info-box-icon text-bg-{{ $color }} shadow-sm"><i class="bi {{ $icon }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $label }}</span>
                        <span class="info-box-number">{{ $value }}</span>
                        @if ($hint)<span class="small text-body-secondary">{{ $hint }}</span>@endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
    <p class="small text-body-secondary">در بازه: {{ number_format($report['consented_in_range']) }} رضایت جدید و {{ number_format($report['opted_out_in_range']) }} لغو رضایت.</p>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">منبع جذب (اولین تماس) — کل دوره</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>منبع</th><th>مخاطب</th><th>با رضایت</th><th>نسبت</th></tr></thead>
                            <tbody>
                                @forelse ($report['by_source'] as $row)
                                    <tr>
                                        <td>{!! $row['source'] ? '<span class="ltr">' . e($row['source']) . '</span>' : '<span class="text-body-secondary">مستقیم / نامشخص</span>' !!}</td>
                                        <td>{{ number_format($row['total']) }}</td>
                                        <td>{{ number_format($row['consent']) }}</td>
                                        <td>{{ $row['total'] ? number_format($row['consent'] / $row['total'] * 100, 1) : 0 }}%</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="4">مخاطبی نیست.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">گروه‌ها — وضعیت فعلی</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead><tr><th>گروه</th><th>عضو</th><th>با رضایت (قابل ارسال تبلیغاتی)</th></tr></thead>
                            <tbody>
                                @forelse ($report['groups'] as $row)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.groups.show', $row['group']) }}">{{ $row['group']->name }}</a>
                                            <span class="badge text-bg-{{ $row['group']->isDynamic() ? 'info' : 'light' }}">{{ $row['group']->isDynamic() ? 'پویا' : 'دستی' }}</span>
                                        </td>
                                        <td>{{ number_format($row['total']) }}</td>
                                        <td>{{ number_format($row['consent']) }}</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="3">گروهی تعریف نشده است.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

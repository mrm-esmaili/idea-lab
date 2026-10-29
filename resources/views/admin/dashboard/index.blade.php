@extends('layouts.admin')

@section('title', 'داشبورد')

@section('content')
    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title h6 mb-0"><i class="bi bi-bell"></i> نیازمند رسیدگی</h2></div>
        @if ($attention === [])
            <div class="card-body text-body-secondary small"><i class="bi bi-check2-circle text-success"></i> مورد معوقی نیست.</div>
        @else
            <div class="list-group list-group-flush">
                @foreach ($attention as $item)
                    <a href="{{ $item['url'] }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                        <i class="bi {{ $item['icon'] }} text-{{ $item['color'] }}"></i>
                        <span class="flex-grow-1">{{ $item['label'] }}</span>
                        <span class="badge text-bg-{{ $item['color'] }}">{{ number_format($item['count']) }}</span>
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    @php($current = $activity['current'])
    @php($previous = $activity['previous'])
    <h2 class="h6 text-body-secondary mb-2">۷ روز اخیر <span class="small">(در مقایسه با ۷ روز قبل از آن)</span></h2>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3 mb-3">
        @foreach ([
            ['key' => 'unique_visitors', 'label' => 'بازدیدکننده یکتا', 'icon' => 'bi-people', 'color' => 'primary'],
            ['key' => 'leads', 'label' => 'سرنخ', 'icon' => 'bi-person-lines-fill', 'color' => 'success', 'hint' => 'نرخ سرنخ: ' . number_format($current['lead_rate'], 2) . '%'],
            ['key' => 'preorders', 'label' => 'درخواست پیش‌خرید', 'icon' => 'bi-bag', 'color' => 'info'],
            ['key' => 'paid_count', 'label' => 'پیش‌پرداخت موفق', 'icon' => 'bi-credit-card', 'color' => 'warning'],
            ['key' => 'paid_rial', 'label' => 'وجه دریافت‌شده', 'icon' => 'bi-cash-stack', 'color' => 'secondary', 'money' => true],
        ] as $stat)
            @php($change = \App\Services\Reports\DashboardService::change($current[$stat['key']], $previous[$stat['key']]))
            <div class="col">
                <div class="info-box h-100">
                    <span class="info-box-icon text-bg-{{ $stat['color'] }} shadow-sm"><i class="bi {{ $stat['icon'] }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $stat['label'] }}</span>
                        <span class="info-box-number">
                            {{ ($stat['money'] ?? false) ? \App\Support\Money::formatRialAsToman($current[$stat['key']]) : number_format($current[$stat['key']]) }}
                        </span>
                        <span class="small text-body-secondary">
                            @if ($change === null)
                                قبلی: {{ ($stat['money'] ?? false) ? \App\Support\Money::formatRialAsToman($previous[$stat['key']]) : number_format($previous[$stat['key']]) }}
                            @else
                                <span @class(['text-success' => $change > 0, 'text-danger' => $change < 0])>{{ $change > 0 ? '+' : '' }}{{ number_format($change, 1) }}%</span>
                            @endif
                            @isset($stat['hint']) — {{ $stat['hint'] }} @endisset
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">ایده‌ها بر اساس وضعیت</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse (config('statuses.idea') as $status => $label)
                        @continue(! isset($ideasByStatus[$status]))
                        <li class="list-group-item d-flex justify-content-between">
                            <x-admin.status-badge group="idea" :value="$status" />
                            <span>{{ number_format($ideasByStatus[$status]) }}</span>
                        </li>
                    @empty
                    @endforelse
                    @if ($ideasByStatus === [])
                        <li class="list-group-item small text-body-secondary">ایده فعالی ثبت نشده است.</li>
                    @endif
                </ul>
                @can('reports.view')
                    <div class="card-footer small"><a href="{{ route('admin.reports.ideas') }}">اقتصاد ایده‌ها</a> — <a href="{{ route('admin.reports.funnel') }}">قیف تبدیل</a></div>
                @endcan
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">صفحات فرود (کل دوره)</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>صفحه</th>
                                    <th>بازدیدکننده</th>
                                    <th>سرنخ</th>
                                    <th>نرخ سرنخ</th>
                                    <th>پیش‌پرداخت</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($landingPages as $page)
                                    @php($s = $pageStats[(string) $page->id] ?? null)
                                    <tr>
                                        <td>
                                            {{ $page->name }}
                                            @if (! $page->is_active)<span class="badge text-bg-secondary">غیرفعال</span>@endif
                                            <div class="small text-body-secondary">{{ $page->idea?->name ?? 'بدون ایده' }}</div>
                                        </td>
                                        <td>{{ number_format($s['unique_visitors'] ?? 0) }}</td>
                                        <td>{{ number_format($s['leads'] ?? 0) }}</td>
                                        <td>{{ number_format($s['lead_rate'] ?? 0, 2) }}%</td>
                                        <td>{{ number_format($s['paid_count'] ?? 0) }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('landing-pages.show', ['slug' => $page->slug]) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" aria-label="مشاهده {{ $page->name }}">
                                                <i class="bi bi-box-arrow-up-left"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="6">هنوز صفحه فرودی ثبت نشده است.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($landingPages->hasPages())
                    <div class="card-footer">{{ $landingPages->links() }}</div>
                @endif
                <div class="card-footer small text-body-secondary">نرخ سرنخ = سرنخ ÷ بازدیدکننده یکتا (کوکی مرورگر)؛ همان تعریف همه گزارش‌ها.</div>
            </div>
        </div>
    </div>
@endsection

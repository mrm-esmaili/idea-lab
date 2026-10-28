@extends('layouts.admin')

@section('title', 'داشبورد اعتبارسنجی')

@section('content')
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-5 g-3 mb-4">
        @foreach ([
            ['label' => 'کل بازدید صفحات', 'value' => number_format($pageViews), 'icon' => 'bi-eye', 'color' => 'primary'],
            ['label' => 'بازدیدکنندگان ثبت‌شده', 'value' => number_format($visitors), 'icon' => 'bi-people', 'color' => 'info'],
            ['label' => 'کل سرنخ‌ها', 'value' => number_format($leads), 'icon' => 'bi-person-lines-fill', 'color' => 'success'],
            ['label' => 'نرخ تبدیل تقریبی', 'value' => number_format($conversionRate, 2) . '%', 'icon' => 'bi-graph-up-arrow', 'color' => 'warning'],
            ['label' => 'کلیک روی CTA', 'value' => number_format($ctaClicks), 'icon' => 'bi-hand-index', 'color' => 'secondary', 'hint' => 'نرخ کلیک: ' . number_format($ctaClickRate, 2) . '%'],
        ] as $stat)
            <div class="col">
                <div class="info-box h-100">
                    <span class="info-box-icon text-bg-{{ $stat['color'] }} shadow-sm">
                        <i class="bi {{ $stat['icon'] }}"></i>
                    </span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $stat['label'] }}</span>
                        <span class="info-box-number">{{ $stat['value'] }}</span>
                        @isset($stat['hint'])
                            <span class="small text-body-secondary">{{ $stat['hint'] }}</span>
                        @endisset
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title h5 mb-0">عملکرد صفحات فرود</h2>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ایده</th>
                            <th>صفحه فرود</th>
                            <th>وضعیت</th>
                            <th>بازدید</th>
                            <th>سرنخ</th>
                            <th>نرخ تبدیل</th>
                            <th>لینک</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($landingPages as $page)
                            @php
                                $rate = $page->page_views_count > 0
                                    ? round(
                                        ($page->leads_count / $page->page_views_count) * 100,
                                        2
                                    )
                                    : 0;
                            @endphp

                            <tr>
                                <td>{{ $page->idea?->name ?? 'بدون ایده' }}</td>
                                <td>{{ $page->name }}</td>
                                <td>
                                    @if ($page->is_active)
                                        <span class="badge text-bg-success">فعال</span>
                                    @else
                                        <span class="badge text-bg-secondary">غیرفعال</span>
                                    @endif
                                </td>
                                <td>{{ number_format($page->page_views_count) }}</td>
                                <td>{{ number_format($page->leads_count) }}</td>
                                <td>{{ number_format($rate, 2) }}%</td>
                                <td>
                                    <a
                                        href="{{ route('landing-pages.show', ['slug' => $page->slug]) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        <i class="bi bi-box-arrow-up-left"></i>
                                        مشاهده
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="7">
                                هنوز صفحه فرودی ثبت نشده است.
                            </x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($landingPages->hasPages())
            <div class="card-footer">
                {{ $landingPages->links() }}
            </div>
        @endif
    </div>

    <p class="text-body-secondary small mt-3">
        نرخ تبدیل بر اساس تعداد سرنخ‌ها تقسیم بر تعداد بازدیدها محاسبه می‌شود.
        بازدید تکراری و سرنخ‌های نامعتبر ممکن است روی این عدد تأثیر بگذارند.
    </p>
@endsection

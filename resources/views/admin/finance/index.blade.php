@extends('layouts.admin')

@section('title', 'گزارش مالی')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">گزارش مالی</li>
@endsection

@php($toman = fn (int $rial) => \App\Support\Money::formatRialAsToman($rial))

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.finance.index') }}" class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="idea" class="form-label">ایده</label>
                    <select id="idea" name="idea" class="form-select">
                        <option value="">همه</option>
                        @foreach ($ideas as $ideaOption)
                            <option value="{{ $ideaOption->id }}" @selected((string) request('idea') === (string) $ideaOption->id)>{{ $ideaOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="from" class="form-label">از تاریخ</label>
                    <input id="from" type="date" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="to" class="form-label">تا تاریخ</label>
                    <input id="to" type="date" name="to" value="{{ request('to') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> اعمال</button>
                    <a href="{{ route('admin.finance.index') }}" class="btn btn-outline-secondary">کل دوره</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-3">
        @foreach ([
            ['label' => 'وجه دریافت‌شده', 'value' => $totals['received'], 'color' => 'primary'],
            ['label' => 'بازپرداخت', 'value' => $totals['refunded'], 'color' => 'danger'],
            ['label' => 'خالص دریافتی', 'value' => $totals['net'], 'color' => 'success', 'hint' => 'درآمد شناسایی‌شده ' . $toman($totals['recognized']) . ' — پیش‌دریافت ' . $toman($totals['deferred'])],
            ['label' => 'هزینه', 'value' => $totals['expenses'], 'color' => 'warning', 'hint' => 'مانده نقدی ' . $toman($totals['cash'])],
        ] as $card)
            <div class="col">
                <div class="card h-100 border-{{ $card['color'] }} border-top-0 border-end-0 border-bottom-0 border-3">
                    <div class="card-body">
                        <div class="small text-body-secondary">{{ $card['label'] }}</div>
                        <div class="fs-5 fw-bold">{{ $toman($card['value']) }}</div>
                        @isset($card['hint'])
                            <div class="small text-body-secondary">{{ $card['hint'] }}</div>
                        @endisset
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-xl-8">
            <div class="card h-100">
                <div class="card-header"><h2 class="card-title h6 mb-0">به تفکیک ایده</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle small">
                            <thead>
                                <tr>
                                    <th>ایده</th>
                                    <th>دریافت</th>
                                    <th>بازپرداخت</th>
                                    <th>خالص</th>
                                    <th>هزینه</th>
                                    <th>مانده نقدی</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($rows as $row)
                                    <tr>
                                        <td>
                                            {{ $row['idea']?->name ?? 'عمومی' }}
                                            @if ($row['idea'])
                                                <x-admin.status-badge group="idea" :value="$row['idea']->status" />
                                            @endif
                                        </td>
                                        <td class="text-nowrap">{{ $toman($row['received']) }}</td>
                                        <td class="text-nowrap">{{ $toman($row['refunded']) }}</td>
                                        <td class="text-nowrap fw-semibold">{{ $toman($row['net']) }}</td>
                                        <td class="text-nowrap">{{ $toman($row['expenses']) }}</td>
                                        <td @class(['text-nowrap', 'text-danger' => $row['cash'] < 0])>{{ $toman($row['cash']) }}</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="6" icon="bi-graph-up">در این بازه تراکنش یا هزینه‌ای ثبت نشده است.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">در انتظار (وضعیت فعلی)</h2></div>
                <ul class="list-group list-group-flush small">
                    <li class="list-group-item d-flex justify-content-between"><span>پرداخت در انتظار تأیید درگاه</span><span>{{ $toman($pending['payments']) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>درخواست بازپرداخت در انتظار بررسی</span><span>{{ $toman($pending['refund_requests']) }}</span></li>
                    <li class="list-group-item d-flex justify-content-between"><span>بازپرداخت تأییدشده واریزنشده</span><span>{{ $toman($pending['approved_unpaid']) }}</span></li>
                </ul>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title h6 mb-0">هزینه‌ها</h2></div>
                <ul class="list-group list-group-flush small">
                    @forelse ($expensesByCategory as $category => $amount)
                        <li class="list-group-item d-flex justify-content-between"><span>{{ \App\Models\Expense::CATEGORIES[$category] ?? $category }}</span><span>{{ $toman($amount) }}</span></li>
                    @empty
                        <li class="list-group-item text-body-secondary">هزینه‌ای ثبت نشده است.</li>
                    @endforelse
                    @if ($expensesByType->isNotEmpty())
                        <li class="list-group-item text-body-secondary">
                            ثابت {{ $toman($expensesByType['fixed'] ?? 0) }} — متغیر {{ $toman($expensesByType['variable'] ?? 0) }}
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title h6 mb-0">تعریف شاخص‌ها (مبنای نقدی)</h2></div>
        <div class="card-body small text-body-secondary">
            <ul class="mb-0">
                <li><strong>وجه دریافت‌شده:</strong> جمع پرداخت‌های تأییدشده درگاه با زمان تأیید در بازه.</li>
                <li><strong>بازپرداخت:</strong> جمع بازپرداخت‌های اجراشده (با مرجع) با تاریخ واریز در بازه؛ درخواست یا تأیید بدون اجرا حساب نمی‌شود.</li>
                <li><strong>خالص دریافتی:</strong> وجه دریافت‌شده منهای بازپرداخت.</li>
                <li><strong>درآمد شناسایی‌شده:</strong> خالص دریافتی ایده‌هایی که «عرضه‌شده» هستند. برای سایر ایده‌ها مبلغ <strong>پیش‌دریافت</strong> است، یعنی تعهد به مشتری تا زمان عرضه.</li>
                <li><strong>هزینه:</strong> هزینه‌های ثبت‌شده با تاریخ وقوع در بازه. <strong>مانده نقدی</strong> = خالص دریافتی منهای هزینه.</li>
                <li><strong>در انتظار:</strong> وضعیت فعلی بدون فیلتر تاریخ.</li>
            </ul>
        </div>
    </div>
@endsection

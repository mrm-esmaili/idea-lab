@extends('layouts.admin')

@section('title', 'گزارش پرداخت‌ها')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">پرداخت‌ها</li>
@endsection

@section('content')
    @if ($duplicateCount > 0)
        <div class="alert alert-danger d-flex align-items-center gap-2">
            <i class="bi bi-exclamation-triangle"></i>
            <span>{{ number_format($duplicateCount) }} سفارش بیش از یک پرداخت موفق دارد و نیازمند بازپرداخت مبلغ اضافه است.</span>
            <a href="{{ route('admin.payments.index', ['duplicates' => 1]) }}" class="alert-link ms-auto">نمایش</a>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.payments.index') }}" class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="idea" class="form-label">ایده</label>
                    <select id="idea" name="idea" class="form-select">
                        <option value="">همه ایده‌ها</option>
                        @foreach ($ideas as $ideaOption)
                            <option value="{{ $ideaOption->id }}" @selected((string) request('idea') === (string) $ideaOption->id)>{{ $ideaOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="status" class="form-label">وضعیت</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">همه</option>
                        @foreach (config('statuses.payment') as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="from" class="form-label">از تاریخ</label>
                    <input id="from" type="date" name="from" value="{{ request('from') }}" @class(['form-control', 'is-invalid' => $errors->has('from')])>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="to" class="form-label">تا تاریخ</label>
                    <input id="to" type="date" name="to" value="{{ request('to') }}" @class(['form-control', 'is-invalid' => $errors->has('to')])>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> فیلتر</button>
                    <a href="{{ route('admin.payments.index') }}" class="btn btn-outline-secondary">پاک‌کردن</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-3">
        @foreach (config('statuses.payment') as $value => $label)
            @php($row = $summary[$value] ?? null)
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <x-admin.status-badge group="payment" :value="$value" />
                            <span class="fs-5 fw-bold">{{ number_format($row->total ?? 0) }}</span>
                        </div>
                        <div class="small text-body-secondary">
                            {{ \App\Support\Money::formatRialAsToman((int) ($row->amount_rial ?? 0)) }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>مشتری</th>
                            <th>ایده</th>
                            <th>مبلغ</th>
                            <th>وضعیت</th>
                            <th>کد پیگیری</th>
                            <th>زمان</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="ltr">{{ $payment->id }}</td>
                                <td>
                                    <a href="{{ route('admin.preorders.show', $payment->preorder_id) }}">{{ $payment->preorder?->name }}</a>
                                    <div class="small text-body-secondary ltr text-end">{{ $payment->preorder?->phone }}</div>
                                </td>
                                <td>{{ $payment->preorder?->landingPage?->idea?->name ?? '—' }}</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($payment->amount_rial) }}</td>
                                <td>
                                    <x-admin.status-badge group="payment" :value="$payment->status" />
                                    @if ($payment->status === 'paid' && in_array($payment->preorder_id, $duplicatePreorderIds, true))
                                        <span class="badge text-bg-danger">تکراری</span>
                                    @endif
                                </td>
                                <td class="ltr">{{ $payment->ref_id ?? '—' }}</td>
                                <td class="ltr small text-nowrap">{{ \App\Support\Format::datetime($payment->created_at) }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="7" icon="bi-credit-card">تراکنشی با این شرایط پیدا نشد.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($payments->hasPages())
            <div class="card-footer">{{ $payments->links() }}</div>
        @endif
    </div>

    <p class="small text-body-secondary mt-3">
        موفق = تأییدشده سمت سرور درگاه. در انتظار = نتیجه هنوز قطعی نیست (از صفحه سفارش قابل استعلام است).
        ناموفق = درگاه پرداخت را رد کرد. لغوشده = کاربر یا درگاه در صفحه پرداخت لغو کرد.
        خلاصه‌ها با فیلتر ایده و تاریخ محاسبه می‌شوند. بازپرداخت‌ها در فاز ۶ اضافه می‌شوند.
    </p>
@endsection

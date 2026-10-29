@extends('layouts.admin')

@section('title', 'پیش‌خرید ' . $preorder->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.preorders.index') }}">پیش‌خریدها</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $preorder->name }}</li>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">سفارش</h2></div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 fw-normal text-body-secondary">نام</dt>
                        <dd class="col-7">{{ $preorder->name }}</dd>

                        <dt class="col-5 fw-normal text-body-secondary">شماره تماس</dt>
                        <dd class="col-7"><a href="tel:{{ $preorder->phone }}" class="ltr">{{ $preorder->phone }}</a></dd>

                        <dt class="col-5 fw-normal text-body-secondary">ایده / صفحه</dt>
                        <dd class="col-7">
                            @if ($preorder->landingPage?->idea)
                                <a href="{{ route('ideas.show', $preorder->landingPage->idea) }}">{{ $preorder->landingPage->idea->name }}</a>
                            @endif
                            <div class="text-body-secondary">{{ $preorder->landingPage?->name }}</div>
                        </dd>

                        <dt class="col-5 fw-normal text-body-secondary">مبلغ سفارش</dt>
                        <dd class="col-7">{{ \App\Support\Money::formatRialAsToman($preorder->amount_rial) }}</dd>

                        <dt class="col-5 fw-normal text-body-secondary">وضعیت پرداخت</dt>
                        <dd class="col-7"><x-admin.status-badge group="preorder_payment" :value="$preorder->payment_status" /></dd>

                        <dt class="col-5 fw-normal text-body-secondary">شرایط پذیرفته‌شده</dt>
                        <dd class="col-7">
                            @if ($preorder->refundPolicy)
                                نسخه {{ $preorder->refundPolicy->version }} — {{ $preorder->refundPolicy->summary() }}
                                <div class="text-body-secondary ltr">{{ \App\Support\Format::datetime($preorder->terms_accepted_at) }}</div>
                            @else
                                <span class="text-body-secondary">ثبت نشده (سفارش قبل از نسخه‌بندی شرایط)</span>
                            @endif
                        </dd>

                        <dt class="col-5 fw-normal text-body-secondary">منبع / کمپین</dt>
                        <dd class="col-7 ltr">{{ $preorder->source ?? '—' }} / {{ $preorder->campaign ?? '—' }}</dd>

                        <dt class="col-5 fw-normal text-body-secondary">زمان ثبت</dt>
                        <dd class="col-7 ltr">{{ \App\Support\Format::datetime($preorder->created_at) }}</dd>

                        @if ($preorder->lead)
                            <dt class="col-5 fw-normal text-body-secondary">سرنخ مرتبط</dt>
                            <dd class="col-7">{{ $preorder->lead->name }} — <x-admin.status-badge group="lead" :value="$preorder->lead->status" /></dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">پیگیری فروش</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.preorders.update', $preorder) }}" class="d-flex gap-2">
                        @csrf
                        @method('PATCH')
                        <select name="status" class="form-select" aria-label="وضعیت پیگیری">
                            @foreach (config('statuses.preorder') as $value => $label)
                                <option value="{{ $value }}" @selected($preorder->status === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-primary">ذخیره</button>
                    </form>
                    <div class="form-text">وضعیت پرداخت را فقط درگاه و سیستم تغییر می‌دهد.</div>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            @error('refund')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">تراکنش‌ها</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>وضعیت</th>
                                    <th>مبلغ</th>
                                    <th>کد پیگیری</th>
                                    <th>کارت</th>
                                    <th>زمان</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($preorder->payments as $payment)
                                    <tr>
                                        <td class="ltr">{{ $payment->id }}</td>
                                        <td><x-admin.status-badge group="payment" :value="$payment->status" /></td>
                                        <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($payment->amount_rial) }}</td>
                                        <td class="ltr">{{ $payment->ref_id ?? '—' }}</td>
                                        <td class="ltr small">{{ $payment->card_pan ?? '—' }}</td>
                                        <td class="ltr small text-nowrap">
                                            {{ \App\Support\Format::datetime($payment->created_at) }}
                                            @if ($payment->verified_at)
                                                <div class="text-success">✓ {{ \App\Support\Format::datetime($payment->verified_at) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($payment->status === \App\Models\Payment::STATUS_PENDING && $payment->authority)
                                                <form method="POST" action="{{ route('admin.payments.verify', $payment) }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">
                                                        <i class="bi bi-arrow-repeat"></i> استعلام از درگاه
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($payment->failure_reason)
                                        <tr>
                                            <td></td>
                                            <td colspan="6" class="small text-body-secondary border-top-0 pt-0">{{ $payment->failure_reason }}</td>
                                        </tr>
                                    @endif
                                @empty
                                    <x-admin.empty-row colspan="7">هنوز تراکنشی برای این سفارش ثبت نشده است.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if ($preorder->payments->where('status', 'paid')->count() > 1)
                    <div class="card-footer text-danger small">
                        <i class="bi bi-exclamation-triangle"></i>
                        این سفارش بیش از یک پرداخت موفق دارد؛ مبلغ اضافه باید بازپرداخت شود.
                    </div>
                @endif
            </div>
            <div class="card mt-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">بازپرداخت</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($preorder->refundRequests as $refundRequest)
                        <li class="list-group-item d-flex align-items-center gap-2 small">
                            @can('finance.manage')
                                <a href="{{ route('admin.refunds.show', $refundRequest) }}" class="ltr">#{{ $refundRequest->id }}</a>
                            @else
                                <span class="ltr">#{{ $refundRequest->id }}</span>
                            @endcan
                            <span>{{ \App\Support\Money::formatRialAsToman($refundRequest->requested_amount_rial) }}</span>
                            <span class="text-body-secondary">{{ $refundRequest->requested_by_type === 'admin' ? 'ثبت مدیر' : 'درخواست مشتری' }}</span>
                            <x-admin.status-badge group="refund_request" :value="$refundRequest->status" class="ms-auto" />
                        </li>
                    @empty
                        <li class="list-group-item small text-body-secondary">درخواست بازپرداختی ثبت نشده است.</li>
                    @endforelse
                </ul>

                @can('finance.manage')
                    @if ($refundable->isNotEmpty())
                        <div class="card-footer">
                            <form method="POST" action="{{ route('admin.refunds.store', $preorder) }}" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-sm-4">
                                    <label for="refund-payment" class="form-label small">پرداخت</label>
                                    <select id="refund-payment" name="payment_id" class="form-select form-select-sm" required>
                                        @foreach ($refundable as $paymentId => $remaining)
                                            <option value="{{ $paymentId }}">#{{ $paymentId }} — قابل بازپرداخت {{ \App\Support\Money::formatRialAsToman($remaining) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <label for="refund-amount" class="form-label small">مبلغ (تومان)</label>
                                    <input id="refund-amount" type="text" inputmode="numeric" name="amount_toman" required
                                        value="{{ number_format(\App\Support\Money::rialToToman($refundable->first())) }}" class="form-control form-control-sm ltr">
                                </div>
                                <div class="col-sm-5">
                                    <label for="refund-reason" class="form-label small">دلیل</label>
                                    <input id="refund-reason" type="text" name="reason" maxlength="2000" required class="form-control form-control-sm"
                                        placeholder="مثلاً پرداخت تکراری یا درخواست تلفنی مشتری">
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">ثبت درخواست بازپرداخت از طرف مدیر</button>
                                    <span class="form-text">پس از ثبت، مانند درخواست مشتری نیاز به تأیید و ثبت اجرا دارد.</span>
                                </div>
                            </form>
                        </div>
                    @endif
                @endcan
            </div>
        </div>
    </div>
@endsection

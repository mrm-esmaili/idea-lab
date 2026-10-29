@extends('layouts.admin')

@section('title', 'درخواست بازپرداخت #' . $refundRequest->id)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.refunds.index') }}">بازپرداخت‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">#{{ $refundRequest->id }}</li>
@endsection

@php
    $preorder = $refundRequest->preorder;
    $payment = $refundRequest->payment;
    $policy = $refundRequest->refundPolicy;
    $toman = fn (?int $rial) => \App\Support\Money::formatRialAsToman($rial);
    $approvedRemaining = ($refundRequest->approved_amount_rial ?? 0) - $executed;
@endphp

@section('content')
    @error('refund')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <div class="row g-3">
        <div class="col-xl-5">
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h2 class="card-title h6 mb-0">درخواست</h2>
                    <x-admin.status-badge group="refund_request" :value="$refundRequest->status" class="ms-auto" />
                </div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 fw-normal text-body-secondary">مشتری</dt>
                        <dd class="col-7">
                            {{ $preorder->name }} — <span class="ltr">{{ $preorder->phone }}</span>
                            @if ($preorder->contact && auth()->user()->can('contacts.manage'))
                                <div><a href="{{ route('admin.contacts.show', $preorder->contact) }}">پرونده مخاطب</a></div>
                            @endif
                        </dd>

                        <dt class="col-5 fw-normal text-body-secondary">سفارش</dt>
                        <dd class="col-7">
                            <a href="{{ route('admin.preorders.show', $preorder) }}">{{ $preorder->landingPage?->idea?->name }}</a>
                            — <x-admin.status-badge group="preorder_payment" :value="$preorder->payment_status" />
                        </dd>

                        <dt class="col-5 fw-normal text-body-secondary">ثبت‌کننده</dt>
                        <dd class="col-7">
                            {{ $refundRequest->requested_by_type === 'admin' ? 'مدیر: ' . ($refundRequest->requester?->name ?? '—') : 'مشتری (حساب کاربری)' }}
                            <div class="text-body-secondary ltr text-end">{{ \App\Support\Format::datetime($refundRequest->created_at) }}</div>
                        </dd>

                        <dt class="col-5 fw-normal text-body-secondary">مبلغ درخواست</dt>
                        <dd class="col-7">{{ $toman($refundRequest->requested_amount_rial) }}</dd>

                        <dt class="col-5 fw-normal text-body-secondary">دلیل</dt>
                        <dd class="col-7">{!! nl2br(e($refundRequest->reason)) !!}</dd>

                        @if ($refundRequest->decided_at)
                            <dt class="col-5 fw-normal text-body-secondary">تصمیم</dt>
                            <dd class="col-7">
                                {{ $refundRequest->decider?->name }} — <span class="ltr">{{ \App\Support\Format::datetime($refundRequest->decided_at) }}</span>
                                @if ($refundRequest->approved_amount_rial)
                                    <div>مبلغ تأییدشده: {{ $toman($refundRequest->approved_amount_rial) }}</div>
                                @endif
                                @if ($refundRequest->decision_note)
                                    <div class="text-body-secondary">{{ $refundRequest->decision_note }}</div>
                                @endif
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">پرداخت و شرایط پذیرفته‌شده</h2></div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 fw-normal text-body-secondary">پرداخت</dt>
                        <dd class="col-7">
                            {{ $toman($payment->amount_rial) }} — کد پیگیری <span class="ltr">{{ $payment->ref_id ?? '—' }}</span>
                            <div class="text-body-secondary ltr text-end">{{ \App\Support\Format::datetime($payment->verified_at) }}</div>
                        </dd>

                        <dt class="col-5 fw-normal text-body-secondary">قابل بازپرداخت</dt>
                        <dd class="col-7 fw-semibold">{{ $toman($paymentRemaining) }}</dd>

                        <dt class="col-5 fw-normal text-body-secondary">شرایط</dt>
                        <dd class="col-7">
                            @if ($policy)
                                نسخه {{ $policy->version }} — {{ $policy->summary() }}
                                @if ($deadline)
                                    <div @class(['text-danger' => now()->gt($deadline), 'text-body-secondary' => now()->lte($deadline)])>
                                        مهلت: <span class="ltr">{{ \App\Support\Format::datetime($deadline, 'Y-m-d') }}</span>
                                        @if (now()->gt($deadline)) (گذشته) @endif
                                    </div>
                                @endif
                                @if ($policy->eligibility)
                                    <div class="mt-1"><strong>معیار:</strong> {{ $policy->eligibility }}</div>
                                @endif
                                <details class="mt-1">
                                    <summary>متن شرایط</summary>
                                    {!! nl2br(e($policy->terms)) !!}
                                </details>
                            @else
                                <span class="text-body-secondary">ثبت نشده (سفارش قدیمی)</span>
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            @if ($refundRequest->status === 'pending')
                <div class="card mb-3 border-warning">
                    <div class="card-header"><h2 class="card-title h6 mb-0">تصمیم</h2></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('admin.refunds.approve', $refundRequest) }}" class="mb-3">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-sm-4">
                                    <label for="approve-amount" class="form-label">مبلغ تأیید (تومان)</label>
                                    <input id="approve-amount" type="text" inputmode="numeric" name="amount_toman" required
                                        value="{{ old('amount_toman', number_format(\App\Support\Money::rialToToman(min($refundRequest->requested_amount_rial, $paymentRemaining)))) }}"
                                        class="form-control ltr">
                                </div>
                                <div class="col-sm-5">
                                    <label for="approve-note" class="form-label">توضیح (اختیاری)</label>
                                    <input id="approve-note" type="text" name="note" maxlength="2000" class="form-control">
                                </div>
                                <div class="col-sm-3">
                                    <button type="submit" class="btn btn-success w-100" onclick="return confirm('درخواست تأیید شود؟')">تأیید</button>
                                </div>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('admin.refunds.reject', $refundRequest) }}">
                            @csrf
                            <div class="row g-2 align-items-end">
                                <div class="col-sm-9">
                                    <label for="reject-note" class="form-label">دلیل رد (برای مشتری نمایش داده می‌شود)</label>
                                    <input id="reject-note" type="text" name="note" maxlength="2000" required
                                        @class(['form-control', 'is-invalid' => $errors->has('note')])>
                                    @error('note')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-sm-3">
                                    <button type="submit" class="btn btn-outline-danger w-100" onclick="return confirm('درخواست رد شود؟')">رد</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endif

            @if ($refundRequest->status === 'approved')
                <div class="card mb-3 border-primary">
                    <div class="card-header"><h2 class="card-title h6 mb-0">ثبت اجرای بازپرداخت</h2></div>
                    <form method="POST" action="{{ route('admin.refunds.execute', $refundRequest) }}">
                        @csrf
                        <div class="card-body">
                            <p class="small text-body-secondary">
                                پس از واریز واقعی وجه، مشخصات آن را ثبت کنید. وضعیت «بازپرداخت‌شده» فقط با این ثبت اعمال می‌شود.
                                باقی‌مانده مبلغ تأییدشده: <strong>{{ $toman($approvedRemaining) }}</strong>
                            </p>
                            <div class="row g-2">
                                <div class="col-sm-4">
                                    <label for="exec-amount" class="form-label">مبلغ واریزشده (تومان)</label>
                                    <input id="exec-amount" type="text" inputmode="numeric" name="amount_toman" required
                                        value="{{ old('amount_toman', number_format(\App\Support\Money::rialToToman($approvedRemaining))) }}"
                                        @class(['form-control', 'ltr', 'is-invalid' => $errors->has('amount_toman')])>
                                    @error('amount_toman')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-sm-4">
                                    <label for="exec-method" class="form-label">روش</label>
                                    <select id="exec-method" name="method" class="form-select" required>
                                        @foreach (\App\Models\Refund::METHODS as $value => $label)
                                            <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <label for="exec-date" class="form-label">تاریخ واریز</label>
                                    <input id="exec-date" type="date" name="refunded_on" required value="{{ old('refunded_on', now()->format('Y-m-d')) }}"
                                        @class(['form-control', 'is-invalid' => $errors->has('refunded_on')])>
                                    @error('refunded_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-sm-6">
                                    <label for="exec-ref" class="form-label">مرجع / شماره پیگیری بانکی <span class="text-danger">*</span></label>
                                    <input id="exec-ref" type="text" name="reference" maxlength="100" required value="{{ old('reference') }}"
                                        @class(['form-control', 'ltr', 'is-invalid' => $errors->has('reference')])>
                                    @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-sm-6">
                                    <label for="exec-note" class="form-label">توضیح</label>
                                    <input id="exec-note" type="text" name="note" maxlength="2000" value="{{ old('note') }}" class="form-control">
                                </div>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-primary" onclick="return confirm('اجرای بازپرداخت ثبت شود؟ این عملیات قابل حذف نیست.')">ثبت اجرای بازپرداخت</button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">اجراهای ثبت‌شده</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle small">
                            <tbody>
                                @forelse ($refundRequest->refunds as $refund)
                                    <tr>
                                        <td class="text-nowrap">{{ $toman($refund->amount_rial) }}</td>
                                        <td>{{ \App\Models\Refund::METHODS[$refund->method] ?? $refund->method }}</td>
                                        <td class="ltr">{{ $refund->reference }}</td>
                                        <td class="ltr text-nowrap">{{ $refund->refunded_on->format('Y-m-d') }}</td>
                                        <td>{{ $refund->executor?->name }}</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="5">هنوز اجرایی ثبت نشده است.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title h6 mb-0">سابقه ممیزی</h2></div>
                <ul class="list-group list-group-flush small">
                    @foreach ($auditLogs as $log)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong>{{ config('statuses.audit_action')[$log->action] ?? $log->action }}</strong>
                                <span class="ltr text-body-secondary">{{ \App\Support\Format::datetime($log->created_at) }}</span>
                            </div>
                            <div class="text-body-secondary">
                                {{ $log->user?->name ?? ($log->contact ? 'مشتری: ' . $log->contact->mobile : 'سیستم') }}
                                @if (isset($log->data['amount_rial']) || isset($log->data['approved_amount_rial']))
                                    — {{ $toman($log->data['amount_rial'] ?? $log->data['approved_amount_rial']) }}
                                @endif
                                @if (! empty($log->data['reference']))
                                    — مرجع <span class="ltr">{{ $log->data['reference'] }}</span>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endsection

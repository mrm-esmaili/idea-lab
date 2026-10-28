@extends('layouts.admin')

@section('title', 'شرایط بازپرداخت')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ideas.show', $idea) }}">{{ $idea->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">شرایط بازپرداخت</li>
@endsection

@section('content')
    @php($refundable = old('is_refundable', $current ? (int) $current->is_refundable : 1))

    <div class="row g-3">
        <div class="col-xl-7">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title h6 mb-0">
                        @if ($current)
                            ویرایش شرایط (نسخه فعلی: {{ $current->version }})
                        @else
                            تعیین شرایط بازپرداخت
                        @endif
                    </h2>
                </div>

                <form method="POST" action="{{ route('ideas.refund-policy.store', $idea) }}">
                    @csrf

                    <div class="card-body">
                        <div class="alert alert-info small">
                            این متن پیش از پرداخت به مشتری نمایش داده می‌شود و مشتری باید آن را بپذیرد.
                            هر ذخیره، نسخه جدیدی می‌سازد؛ سفارش‌های قبلی همان نسخه‌ای را نگه می‌دارند که پذیرفته‌اند.
                        </div>

                        <div class="mb-3">
                            <div class="form-label">نوع</div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_refundable" id="refundable_yes" value="1" @checked((string) $refundable === '1')>
                                <label class="form-check-label" for="refundable_yes">قابل بازپرداخت</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="is_refundable" id="refundable_no" value="0" @checked((string) $refundable === '0')>
                                <label class="form-check-label" for="refundable_no">غیرقابل بازپرداخت</label>
                            </div>
                            @error('is_refundable')<div class="text-danger small">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-3" style="max-width: 260px">
                            <label for="refund_window_days" class="form-label">مهلت درخواست (روز پس از پرداخت)</label>
                            <input
                                id="refund_window_days" type="number" min="1" max="365" name="refund_window_days"
                                value="{{ old('refund_window_days', $current?->refund_window_days) }}"
                                @class(['form-control', 'is-invalid' => $errors->has('refund_window_days')])
                            >
                            @error('refund_window_days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">فقط برای حالت قابل بازپرداخت.</div>
                        </div>

                        <div class="mb-3">
                            <label for="eligibility" class="form-label">معیار واجد شرایط بودن (داخلی، برای بررسی مدیر)</label>
                            <textarea
                                id="eligibility" name="eligibility" rows="2" maxlength="2000"
                                placeholder="مثلاً: محصول تا تاریخ اعلام‌شده عرضه نشود، یا مشتری پیش از عرضه انصراف دهد."
                                @class(['form-control', 'is-invalid' => $errors->has('eligibility')])
                            >{{ old('eligibility', $current?->eligibility) }}</textarea>
                            @error('eligibility')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-0">
                            <label for="terms" class="form-label">متن نمایش داده‌شده به مشتری <span class="text-danger">*</span></label>
                            <textarea
                                id="terms" name="terms" rows="6" maxlength="5000" required
                                @class(['form-control', 'is-invalid' => $errors->has('terms')])
                            >{{ old('terms', $current?->terms) }}</textarea>
                            @error('terms')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>

                    <div class="card-footer d-flex gap-2">
                        <button type="submit" class="btn btn-primary">ذخیره نسخه جدید</button>
                        <a href="{{ route('ideas.show', $idea) }}" class="btn btn-outline-secondary">بازگشت</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-xl-5">
            <div class="card">
                <div class="card-header"><h2 class="card-title h6 mb-0">تاریخچه نسخه‌ها</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($policies as $policy)
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <strong>نسخه {{ $policy->version }}</strong>
                                @if ($loop->first)
                                    <span class="badge text-bg-success">فعلی</span>
                                @endif
                            </div>
                            <div class="small">{{ $policy->summary() }}</div>
                            <details class="small mt-1">
                                <summary>متن شرایط</summary>
                                <div class="mt-1">{!! nl2br(e($policy->terms)) !!}</div>
                            </details>
                            <div class="small text-body-secondary mt-1">
                                {{ $policy->creator?->name ?? '—' }}
                                — <span class="ltr">{{ \App\Support\Format::datetime($policy->created_at) }}</span>
                                — {{ number_format($policy->preorders_count) }} سفارش
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary small">هنوز نسخه‌ای ثبت نشده است.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

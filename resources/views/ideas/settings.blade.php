@extends('layouts.admin')

@section('title', 'تنظیمات ایده')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ideas.show', $idea) }}">{{ $idea->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">تنظیمات</li>
@endsection

@section('content')
    @php($toman = fn (?int $rial) => $rial === null ? '' : number_format(\App\Support\Money::rialToToman($rial)))

    <form method="POST" action="{{ route('ideas.settings.update', $idea) }}" style="max-width: 900px">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">بودجه و بازه آزمایش</h2></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="budget_toman" class="form-label">بودجه آزمایش (تومان)</label>
                        <input
                            id="budget_toman" type="text" inputmode="numeric" name="budget_toman"
                            value="{{ old('budget_toman', $toman($idea->budget_rial)) }}"
                            @class(['form-control', 'ltr', 'is-invalid' => $errors->has('budget_toman')])
                        >
                        @error('budget_toman')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="test_starts_on" class="form-label">شروع آزمایش</label>
                        <input
                            id="test_starts_on" type="date" name="test_starts_on"
                            value="{{ old('test_starts_on', $idea->test_starts_on?->format('Y-m-d')) }}"
                            @class(['form-control', 'is-invalid' => $errors->has('test_starts_on')])
                        >
                        @error('test_starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label for="test_ends_on" class="form-label">پایان آزمایش</label>
                        <input
                            id="test_ends_on" type="date" name="test_ends_on"
                            value="{{ old('test_ends_on', $idea->test_ends_on?->format('Y-m-d')) }}"
                            @class(['form-control', 'is-invalid' => $errors->has('test_ends_on')])
                        >
                        @error('test_ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">حدنصاب موفقیت</h2></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="lead_goal" class="form-label">حدنصاب سرنخ</label>
                        <input
                            id="lead_goal" type="text" inputmode="numeric" name="lead_goal"
                            value="{{ old('lead_goal', $idea->lead_goal) }}"
                            @class(['form-control', 'ltr', 'is-invalid' => $errors->has('lead_goal')])
                        >
                        @error('lead_goal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="paid_preorder_goal" class="form-label">حدنصاب پیش‌خرید پرداخت‌شده</label>
                        <input
                            id="paid_preorder_goal" type="text" inputmode="numeric" name="paid_preorder_goal"
                            value="{{ old('paid_preorder_goal', $idea->paid_preorder_goal) }}"
                            @class(['form-control', 'ltr', 'is-invalid' => $errors->has('paid_preorder_goal')])
                        >
                        @error('paid_preorder_goal')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="form-text">پیشرفت نسبت به حدنصاب در صفحه ایده نمایش داده می‌شود. تصمیم نهایی با مدیر است.</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">پیش‌خرید</h2></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="preorder_amount_toman" class="form-label">مبلغ پیش‌پرداخت (تومان)</label>
                        <input
                            id="preorder_amount_toman" type="text" inputmode="numeric" name="preorder_amount_toman"
                            value="{{ old('preorder_amount_toman', $toman($idea->preorder_amount_rial)) }}"
                            @class(['form-control', 'ltr', 'is-invalid' => $errors->has('preorder_amount_toman')])
                        >
                        @error('preorder_amount_toman')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">
                            خالی بماند تا پیش‌خرید بسته باشد. تغییر مبلغ فقط روی سفارش‌های جدید اثر دارد.
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-label">شرایط بازپرداخت</div>
                        @if ($idea->currentRefundPolicy)
                            <div>
                                نسخه {{ $idea->currentRefundPolicy->version }} —
                                {{ $idea->currentRefundPolicy->summary() }}
                            </div>
                        @else
                            <div class="text-danger">تعیین نشده؛ تا تعیین آن پیش‌خرید بسته است.</div>
                        @endif
                        <a href="{{ route('ideas.refund-policy', $idea) }}" class="btn btn-sm btn-outline-secondary mt-2">
                            <i class="bi bi-shield-check"></i> مدیریت شرایط بازپرداخت
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
            <a href="{{ route('ideas.show', $idea) }}" class="btn btn-outline-secondary">انصراف</a>
        </div>
    </form>
@endsection

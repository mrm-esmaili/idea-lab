@extends('layouts.admin')

@section('title', $experiment->title)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.experiments.index') }}">آزمایش‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $experiment->title }}</li>
@endsection

@section('page-actions')
    <div class="d-flex flex-wrap gap-2">
        @if ($status === 'planned')
            <a href="{{ route('admin.experiments.edit', $experiment) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> ویرایش</a>
        @endif
        <a href="{{ route('admin.experiments.create', ['from' => $experiment->id]) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-copy"></i> آزمایش بعدی بر این اساس</a>
        @unless (in_array($status, ['decided', 'cancelled'], true))
            <form method="POST" action="{{ route('admin.experiments.cancel', $experiment) }}" data-confirm="آزمایش لغو شود؟" data-confirm-variant="danger">
                @csrf
                <button type="submit" class="btn btn-sm btn-outline-danger">لغو</button>
            </form>
        @endunless
    </div>
@endsection

@php
    $evaluation = $result['evaluation'];
    $totals = $result['totals'];
@endphp

@section('content')
    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title h6 mb-0">تعریف</h2>
                    <x-admin.status-badge group="experiment" :value="$status" />
                </div>
                <div class="card-body small">
                    <dl class="mb-0">
                        <dt>ایده</dt>
                        <dd><a href="{{ route('ideas.show', $experiment->idea) }}">{{ $experiment->idea->name }}</a></dd>
                        <dt>فرضیه</dt>
                        <dd style="white-space: pre-line">{{ $experiment->hypothesis }}</dd>
                        <dt>هدف</dt>
                        <dd style="white-space: pre-line">{{ $experiment->goal }}</dd>
                        <dt>بازه</dt>
                        <dd>{{ \App\Support\Format::date($experiment->starts_on) }} تا {{ \App\Support\Format::date($experiment->ends_on) }}</dd>
                        <dt>دامنه داده</dt>
                        <dd>
                            @forelse ($experiment->campaigns as $campaign)
                                <div><a href="{{ route('admin.campaigns.show', $campaign) }}">{{ $campaign->name }}</a> <span class="ltr text-body-secondary">{{ $campaign->slug }}</span></div>
                            @empty
                                همه ترافیک صفحات ایده
                            @endforelse
                        </dd>
                        <dt>بودجه</dt>
                        <dd>{{ $experiment->budget_rial ? \App\Support\Money::formatRialAsToman($experiment->budget_rial) : '—' }}</dd>
                        <dt>حداقل نمونه</dt>
                        <dd>{{ number_format($experiment->min_sample) }} بازدیدکننده یکتا</dd>
                        <dt>ثبت</dt>
                        <dd class="mb-0">{{ $experiment->creator?->name }} — <span class="ltr">{{ \App\Support\Format::datetime($experiment->created_at) }}</span></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">ارزیابی معیارها</h2></div>
                <div class="card-body">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @if ($status === 'planned')
                            <span class="badge text-bg-info">آزمایش هنوز شروع نشده</span>
                        @else
                            @unless ($evaluation['sample_ok'])
                                <span class="badge text-bg-warning">داده ناکافی: {{ number_format($totals['unique_visitors']) }} از {{ number_format($experiment->min_sample) }} بازدیدکننده</span>
                            @endunless
                            @if ($evaluation['stop_triggered'])
                                <span class="badge text-bg-danger">معیار توقف برقرار است</span>
                            @endif
                            @if ($evaluation['budget_exceeded'])
                                <span class="badge text-bg-danger">بودجه تمام شده</span>
                            @endif
                            @if ($evaluation['success_met'])
                                <span class="badge text-bg-success">همه معیارهای موفقیت برقرار است</span>
                            @elseif (! $evaluation['stop_triggered'])
                                <span class="badge text-bg-secondary">معیارهای موفقیت هنوز برقرار نیست</span>
                            @endif
                        @endif
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr><th>نوع</th><th>معیار</th><th>هدف</th><th>مقدار فعلی</th><th></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($evaluation['criteria'] as $criterion)
                                    <tr>
                                        <td>{{ $criterion['kind'] === 'success' ? 'موفقیت' : 'توقف' }}</td>
                                        <td>{{ $metricDefs[$criterion['metric']][0] }}</td>
                                        <td class="text-nowrap">{{ $criterion['operator'] === 'gte' ? 'حداقل' : 'حداکثر' }} {{ \App\Support\Format::metric($criterion['metric'], $criterion['target']) }}</td>
                                        <td class="text-nowrap">{{ \App\Support\Format::metric($criterion['metric'], $criterion['value']) }}</td>
                                        <td>
                                            @if ($criterion['met'] === null)
                                                <span class="text-body-secondary small">قابل محاسبه نیست</span>
                                            @elseif ($criterion['met'])
                                                <i class="bi bi-check-circle-fill text-{{ $criterion['kind'] === 'success' ? 'success' : 'danger' }}" aria-label="برقرار"></i>
                                            @else
                                                <i class="bi bi-circle text-body-secondary" aria-label="برقرار نیست"></i>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-body-secondary mt-2 mb-0">ارزیابی خودکار فقط کمک به تصمیم است؛ تصمیم نهایی را مدیر ثبت می‌کند.</p>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-block">
                    <h2 class="card-title h6 mb-1 float-none">نتایج بازه آزمایش</h2>
                    <div class="small text-body-secondary">
                        از <span class="ltr d-inline-block">{{ \App\Support\Format::datetime(\Illuminate\Support\Carbon::parse($result['from'])) }}</span> تا <span class="ltr d-inline-block">{{ \App\Support\Format::datetime(\Illuminate\Support\Carbon::parse($result['to'])) }}</span>
                        — فقط داده ثبت‌شده در این بازه
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>شاخص</th>
                                    <th>جمع</th>
                                    @foreach ($experiment->campaigns as $campaign)
                                        <th class="text-nowrap">{{ $campaign->name }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($metricDefs as $key => [$label, $unit, $definition])
                                    <tr>
                                        <td>
                                            {{ $label }}
                                            <div class="small text-body-secondary d-none d-md-block">{{ $definition }}</div>
                                        </td>
                                        <td class="fw-semibold text-nowrap">{{ \App\Support\Format::metric($key, $totals[$key]) }}</td>
                                        @foreach ($experiment->campaigns as $campaign)
                                            <td class="text-nowrap">{{ \App\Support\Format::metric($key, $result['campaigns'][$campaign->id][$key] ?? null) }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @can('experiments.decide')
                @unless (in_array($status, ['planned', 'cancelled'], true))
                    <div class="card mb-3 border-primary">
                        <div class="card-header"><h2 class="card-title h6 mb-0">ثبت تصمیم</h2></div>
                        <form method="POST" action="{{ route('admin.experiments.decide', $experiment) }}" data-confirm="تصمیم «{decision}» ثبت شود؟&#10;شاخص‌های همین لحظه همراه تصمیم ذخیره می‌شود و تصمیم قابل ویرایش نیست.">
                            @csrf
                            <div class="card-body">
                                <div class="mb-3">
                                    @foreach (\App\Models\ExperimentDecision::DECISIONS as $key => $label)
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="decision" id="decision-{{ $key }}" value="{{ $key }}" required @checked(old('decision') === $key)>
                                            <label class="form-check-label" for="decision-{{ $key }}">{{ $label }}</label>
                                        </div>
                                    @endforeach
                                    @error('decision')<div class="text-danger small">{{ $message }}</div>@enderror
                                </div>
                                <div class="mb-3">
                                    <label for="reason" class="form-label">دلیل و شواهد</label>
                                    <textarea id="reason" name="reason" rows="3" minlength="10" maxlength="5000" required @class(['form-control', 'is-invalid' => $errors->has('reason')])>{{ old('reason') }}</textarea>
                                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    <div class="form-text">شاخص‌های همین لحظه همراه تصمیم ذخیره می‌شود.</div>
                                </div>
                                <div style="max-width: 240px">
                                    <label for="review_on" class="form-label">تاریخ بازبینی نتیجه</label>
                                    <input id="review_on" type="date" name="review_on" min="{{ now(config('app.display_timezone'))->addDay()->toDateString() }}" value="{{ old('review_on') }}" @class(['form-control', 'is-invalid' => $errors->has('review_on')])>
                                    @error('review_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">ثبت تصمیم</button>
                            </div>
                        </form>
                    </div>
                @endunless
            @endcan

            @foreach ($experiment->decisions as $decision)
                @php($snap = $decision->snapshot['totals'] ?? [])
                <div class="card mb-3" id="decision-{{ $decision->id }}">
                    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                        <h2 class="card-title h6 mb-0">
                            تصمیم: <x-admin.status-badge group="experiment_decision" :value="$decision->decision" />
                        </h2>
                        <span class="small">{{ $decision->decider?->name }} — <span class="ltr">{{ \App\Support\Format::datetime($decision->created_at) }}</span></span>
                    </div>
                    <div class="card-body small">
                        <p style="white-space: pre-line">{{ $decision->reason }}</p>
                        <div class="fw-semibold mb-1">شاخص‌ها در زمان تصمیم</div>
                        <div class="row row-cols-2 row-cols-md-4 g-2 mb-2">
                            @foreach (['spent_rial', 'unique_visitors', 'leads', 'lead_rate', 'paid_count', 'paid_rial', 'cost_per_lead_rial', 'cost_per_paid_rial'] as $key)
                                <div>
                                    <div class="text-body-secondary">{{ $metricDefs[$key][0] }}</div>
                                    <div>{{ \App\Support\Format::metric($key, $snap[$key] ?? null) }}</div>
                                </div>
                            @endforeach
                        </div>
                        @if ($decision->review_on)
                            <div>بازبینی: <span class="ltr">{{ \App\Support\Format::date($decision->review_on) }}</span>
                                @if ($decision->isReviewDue())<span class="badge text-bg-warning">سررسیده</span>@endif
                            </div>
                        @endif
                    </div>

                    @if ($decision->reviewed_at)
                        <div class="card-footer small">
                            <div class="fw-semibold">ارزیابی پس از تصمیم — {{ \App\Models\ExperimentDecision::OUTCOMES[$decision->outcome] }}</div>
                            <div><strong>اقدام انجام‌شده:</strong> {{ $decision->action_taken }}</div>
                            @if ($decision->outcome_note)<div>{{ $decision->outcome_note }}</div>@endif
                            @php($after = $decision->review_snapshot['totals'] ?? [])
                            <div class="text-body-secondary">
                                از روز تصمیم تا ارزیابی: {{ \App\Support\Format::metric('leads', $after['leads'] ?? null) }} سرنخ،
                                {{ \App\Support\Format::metric('paid_count', $after['paid_count'] ?? null) }} پیش‌پرداخت،
                                هزینه {{ \App\Support\Format::metric('spent_rial', $after['spent_rial'] ?? null) }}
                            </div>
                            <div class="text-body-secondary">{{ $decision->reviewer?->name }} — <span class="ltr">{{ \App\Support\Format::datetime($decision->reviewed_at) }}</span></div>
                        </div>
                    @else
                        @php($after = $postDecision[$decision->id] ?? null)
                        <div class="card-footer small">
                            @if ($after)
                                <div class="text-body-secondary mb-2">
                                    از روز تصمیم تا امروز: {{ \App\Support\Format::metric('leads', $after['leads']) }} سرنخ،
                                    {{ \App\Support\Format::metric('paid_count', $after['paid_count']) }} پیش‌پرداخت،
                                    هزینه {{ \App\Support\Format::metric('spent_rial', $after['spent_rial']) }}
                                </div>
                            @endif
                            @can('experiments.decide')
                                <details @if ($decision->isReviewDue()) open @endif>
                                    <summary>ثبت ارزیابی پس از تصمیم</summary>
                                    <form method="POST" action="{{ route('admin.experiments.review', $decision) }}" class="mt-2">
                                        @csrf
                                        <div class="mb-2">
                                            <label for="action-{{ $decision->id }}" class="form-label">اقدام انجام‌شده</label>
                                            <textarea id="action-{{ $decision->id }}" name="action_taken" rows="2" maxlength="5000" required class="form-control"></textarea>
                                        </div>
                                        <div class="mb-2">
                                            @foreach (\App\Models\ExperimentDecision::OUTCOMES as $key => $label)
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="radio" name="outcome" id="outcome-{{ $decision->id }}-{{ $key }}" value="{{ $key }}" required>
                                                    <label class="form-check-label" for="outcome-{{ $decision->id }}-{{ $key }}">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                        <div class="mb-2">
                                            <label for="note-{{ $decision->id }}" class="form-label">توضیح نتیجه</label>
                                            <textarea id="note-{{ $decision->id }}" name="outcome_note" rows="2" maxlength="5000" class="form-control"></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-sm btn-primary">ثبت ارزیابی</button>
                                    </form>
                                </details>
                            @endcan
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'مقایسه کمپین‌ها')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.index') }}">کمپین‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">مقایسه</li>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-lg-6">
                    <label for="campaigns" class="form-label">کمپین‌ها (حداکثر ۱۰)</label>
                    <select id="campaigns" name="campaigns[]" multiple size="5" class="form-select">
                        @foreach ($campaigns as $campaign)
                            <option value="{{ $campaign->id }}" @selected($selected->contains('id', $campaign->id))>
                                {{ $campaign->name }} — {{ $campaign->landingPage?->idea?->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label for="from" class="form-label">از تاریخ</label>
                    <input id="from" type="date" name="from" value="{{ $from }}" class="form-control">
                </div>
                <div class="col-sm-4 col-lg-2">
                    <label for="to" class="form-label">تا تاریخ</label>
                    <input id="to" type="date" name="to" value="{{ $to }}" class="form-control">
                </div>
                <div class="col-sm-4 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-bar-chart"></i> مقایسه</button>
                </div>
            </form>
        </div>
    </div>

    @if ($selected->isEmpty())
        <div class="alert alert-light border">کمپین‌های مورد نظر را انتخاب کنید.</div>
    @else
        <div class="card mb-3">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>شاخص</th>
                                @foreach ($selected as $campaign)
                                    <th class="text-nowrap">
                                        <a href="{{ route('admin.campaigns.show', $campaign) }}">{{ $campaign->name }}</a>
                                        <div class="small fw-normal text-body-secondary">{{ $campaign->landingPage?->idea?->name }}</div>
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($metricDefs as $key => [$label, $unit])
                                @php($values = $selected->map(fn ($c) => $rows[$c->id][$key] ?? null))
                                @php($best = in_array($key, ['spent_rial'], true) ? null : (str_starts_with($key, 'cost_') ? $values->filter(fn ($v) => $v !== null)->min() : $values->max()))
                                <tr>
                                    <td>{{ $label }}</td>
                                    @foreach ($selected as $campaign)
                                        @php($value = $rows[$campaign->id][$key] ?? null)
                                        <td @class(['text-nowrap', 'fw-bold text-success' => $selected->count() > 1 && $value !== null && $best !== null && $value == $best && $value != 0])>
                                            {{ \App\Support\Format::metric($key, $value) }}
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card">
        <div class="card-header"><h2 class="card-title h6 mb-0">تعریف شاخص‌ها (برای همه کمپین‌ها یکسان)</h2></div>
        <div class="card-body small">
            <ul class="mb-2">
                @foreach ($metricDefs as [$label, $unit, $definition])
                    <li><strong>{{ $label }}:</strong> {{ $definition }}.</li>
                @endforeach
            </ul>
            <p class="mb-0 text-body-secondary">
                داده هر کمپین طبق قاعده انتساب (صفحه فرود کمپین + کد <span class="ltr">utm_campaign</span>) و فقط در بازه انتخابی شمرده می‌شود.
                هزینه فقط از هزینه‌های ثبت‌شده برای همان کمپین است؛ بودجه برنامه‌ریزی‌شده هزینه حساب نمی‌شود. بهترین مقدار هر ردیف پررنگ است.
            </p>
        </div>
    </div>
@endsection

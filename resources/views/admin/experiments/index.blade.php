@extends('layouts.admin')

@section('title', 'آزمایش‌ها و تصمیم‌ها')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">آزمایش‌ها</li>
@endsection

@section('page-actions')
    <a href="{{ route('admin.experiments.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> آزمایش جدید</a>
@endsection

@section('content')
    @if ($dueReviews->isNotEmpty())
        <div class="card mb-3 border-warning">
            <div class="card-header"><h2 class="card-title h6 mb-0"><i class="bi bi-alarm"></i> بازبینی‌های سررسیده</h2></div>
            <ul class="list-group list-group-flush">
                @foreach ($dueReviews as $decision)
                    <li class="list-group-item d-flex flex-wrap justify-content-between gap-2">
                        <span>
                            <a href="{{ route('admin.experiments.show', $decision->experiment_id) }}#decision-{{ $decision->id }}">{{ $decision->experiment->title }}</a>
                            <span class="text-body-secondary small">— {{ $decision->experiment->idea->name }}</span>
                            <x-admin.status-badge group="experiment_decision" :value="$decision->decision" />
                        </span>
                        <span class="small ltr">{{ $decision->review_on->toDateString() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-sm-5 col-lg-3">
                    <label for="f-idea" class="form-label">ایده</label>
                    <select id="f-idea" name="idea" class="form-select">
                        <option value="">همه</option>
                        @foreach ($ideas as $idea)
                            <option value="{{ $idea->id }}" @selected((int) ($filters['idea'] ?? 0) === $idea->id)>{{ $idea->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-4 col-lg-3">
                    <label for="f-status" class="form-label">وضعیت</label>
                    <select id="f-status" name="status" class="form-select">
                        <option value="">همه</option>
                        @foreach (config('statuses.experiment') as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-3 col-lg-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> اعمال</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>آزمایش</th>
                            <th>ایده</th>
                            <th>بازه</th>
                            <th>وضعیت</th>
                            <th>آخرین تصمیم</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($experiments as $experiment)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.experiments.show', $experiment) }}">{{ $experiment->title }}</a>
                                    <div class="small text-body-secondary">{{ $experiment->campaigns->isEmpty() ? 'همه ترافیک ایده' : $experiment->campaigns->pluck('name')->join('، ') }}</div>
                                </td>
                                <td>{{ $experiment->idea->name }}</td>
                                <td class="ltr small text-nowrap">{{ $experiment->starts_on->toDateString() }} → {{ $experiment->ends_on->toDateString() }}</td>
                                <td><x-admin.status-badge group="experiment" :value="$experiment->status()" /></td>
                                <td>
                                    @if ($last = $experiment->decisions->first())
                                        <x-admin.status-badge group="experiment_decision" :value="$last->decision" />
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="5" icon="bi-clipboard-data">هنوز آزمایشی تعریف نشده است.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

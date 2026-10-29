@extends('layouts.admin')

@section('title', request()->boolean('archived') ? 'ایده‌های بایگانی‌شده' : 'ایده‌ها')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">ایده‌ها</li>
@endsection

@section('page-actions')
    <a href="{{ route('ideas.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i>
        ایجاد ایده جدید
    </a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('ideas.index') }}" class="row g-2 align-items-end">
                @if (request()->boolean('archived'))
                    <input type="hidden" name="archived" value="1">
                @endif

                <div class="col-sm-6 col-lg-4">
                    <label for="q" class="form-label">جست‌وجوی نام</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" maxlength="100" class="form-control">
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="status" class="form-label">وضعیت</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">همه وضعیت‌ها</option>
                        @foreach (config('statuses.idea') as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> فیلتر</button>
                    <a
                        href="{{ route('ideas.index', request()->boolean('archived') ? ['archived' => 1] : []) }}"
                        class="btn btn-outline-secondary"
                    >پاک‌کردن فیلتر</a>
                </div>

                <div class="col-auto ms-auto">
                    @if (request()->boolean('archived'))
                        <a href="{{ route('ideas.index') }}" class="btn btn-link"><i class="bi bi-arrow-right"></i> ایده‌های فعال</a>
                    @else
                        <a href="{{ route('ideas.index', ['archived' => 1]) }}" class="btn btn-link"><i class="bi bi-archive"></i> بایگانی</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>نام ایده</th>
                            <th>وضعیت</th>
                            <th>صفحات فرود</th>
                            <th>سرنخ</th>
                            <th>پیش‌خرید</th>
                            <th>تاریخ ایجاد</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($ideas as $idea)
                            <tr>
                                <td>
                                    <a href="{{ route('ideas.show', $idea) }}" class="fw-semibold">{{ $idea->name }}</a>
                                    @if ($idea->description)
                                        <div class="small text-body-secondary">
                                            {{ \Illuminate\Support\Str::limit($idea->description, 100) }}
                                        </div>
                                    @endif
                                </td>
                                <td><x-admin.status-badge group="idea" :value="$idea->status" /></td>
                                <td>{{ number_format($idea->landing_pages_count) }}</td>
                                <td>{{ number_format($idea->leads_count) }}</td>
                                <td>{{ number_format($idea->preorders_count) }}</td>
                                <td class="ltr text-nowrap">{{ \App\Support\Format::datetime($idea->created_at, 'Y-m-d') }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="6" icon="bi-lightbulb">
                                ایده‌ای با این شرایط پیدا نشد.
                            </x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($ideas->hasPages())
            <div class="card-footer">{{ $ideas->links() }}</div>
        @endif
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'درخواست‌های پیش‌خرید')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">پیش‌خریدها</li>
@endsection

@section('content')
    @php($statuses = config('statuses.preorder'))

    <div class="card mb-3">
        <div class="card-body">
            <form
                method="GET"
                action="{{ route('admin.preorders.index') }}"
                class="row g-2 align-items-end"
            >
                <div class="col-sm-6 col-lg-3">
                    <label for="idea" class="form-label">ایده</label>
                    <select id="idea" name="idea" class="form-select">
                        <option value="">همه ایده‌ها</option>
                        @foreach ($ideas as $ideaOption)
                            <option value="{{ $ideaOption->id }}" @selected((string) request('idea') === (string) $ideaOption->id)>
                                {{ $ideaOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="status" class="form-label">وضعیت</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">همه</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i>
                        فیلتر
                    </button>
                    <a href="{{ route('admin.preorders.index') }}" class="btn btn-outline-secondary">
                        پاک‌کردن فیلتر
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title h6 mb-0">
                تعداد نتایج: {{ number_format($preorders->total()) }}
            </h2>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>شماره تماس</th>
                            <th>ایده</th>
                            <th>صفحه فرود</th>
                            <th>کمپین</th>
                            <th>تاریخ ثبت</th>
                            <th>وضعیت</th>
                            <th class="table-actions">تغییر وضعیت</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($preorders as $preorder)
                            <tr>
                                <td>{{ $preorder->name }}</td>
                                <td>
                                    <a href="tel:{{ $preorder->phone }}" class="ltr">{{ $preorder->phone }}</a>
                                </td>
                                <td>{{ $preorder->landingPage?->idea?->name ?? '—' }}</td>
                                <td>{{ $preorder->landingPage?->name ?? '—' }}</td>
                                <td class="ltr">{{ $preorder->campaign ?? '—' }}</td>
                                <td class="ltr text-nowrap">{{ \App\Support\Format::datetime($preorder->created_at) }}</td>
                                <td><x-admin.status-badge group="preorder" :value="$preorder->status" /></td>
                                <td>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.preorders.update', $preorder) }}"
                                        class="d-flex gap-1"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <select
                                            name="status"
                                            class="form-select form-select-sm"
                                            aria-label="وضعیت پیش‌خرید {{ $preorder->name }}"
                                        >
                                            @foreach ($statuses as $value => $label)
                                                <option value="{{ $value }}" @selected($preorder->status === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <button type="submit" class="btn btn-sm btn-primary">
                                            ذخیره
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="8">
                                درخواست پیش‌خریدی با این شرایط پیدا نشد.
                            </x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($preorders->hasPages())
            <div class="card-footer">
                {{ $preorders->links() }}
            </div>
        @endif
    </div>
@endsection

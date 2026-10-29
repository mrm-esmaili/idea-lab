@extends('layouts.admin')

@section('title', 'هزینه‌ها')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">هزینه‌ها</li>
@endsection

@section('page-actions')
    <a href="{{ route('admin.expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> ثبت هزینه</a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.expenses.index') }}" class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="idea" class="form-label">ایده</label>
                    <select id="idea" name="idea" class="form-select">
                        <option value="">همه</option>
                        @foreach ($ideas as $ideaOption)
                            <option value="{{ $ideaOption->id }}" @selected((string) request('idea') === (string) $ideaOption->id)>{{ $ideaOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="category" class="form-label">دسته</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">همه</option>
                        @foreach (\App\Models\Expense::CATEGORIES as $value => $label)
                            <option value="{{ $value }}" @selected(request('category') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="from" class="form-label">از تاریخ</label>
                    <input id="from" type="date" name="from" value="{{ request('from') }}" class="form-control">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="to" class="form-label">تا تاریخ</label>
                    <input id="to" type="date" name="to" value="{{ request('to') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> فیلتر</button>
                    <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary">پاک‌کردن</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title h6 mb-0">جمع: {{ \App\Support\Money::formatRialAsToman($total) }}</h2>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>تاریخ</th>
                            <th>شرح</th>
                            <th>ایده / کمپین</th>
                            <th>دسته</th>
                            <th>نوع</th>
                            <th>مبلغ</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($expenses as $expense)
                            <tr>
                                <td class="ltr text-nowrap small">{{ \App\Support\Format::date($expense->incurred_on) }}</td>
                                <td>{{ $expense->description }}<div class="small text-body-secondary">{{ $expense->creator?->name }}</div></td>
                                <td class="small">
                                    {{ $expense->idea?->name ?? 'عمومی' }}
                                    @if ($expense->campaign)
                                        <div class="text-body-secondary">{{ $expense->campaign->name }}</div>
                                    @endif
                                </td>
                                <td>{{ \App\Models\Expense::CATEGORIES[$expense->category] ?? $expense->category }}</td>
                                <td>{{ \App\Models\Expense::COST_TYPES[$expense->cost_type] ?? $expense->cost_type }}</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($expense->amount_rial) }}</td>
                                <td class="text-nowrap text-end">
                                    <a href="{{ route('admin.expenses.edit', $expense) }}" class="btn btn-sm btn-outline-secondary" aria-label="ویرایش"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('admin.expenses.destroy', $expense) }}" class="d-inline" onsubmit="return confirm('هزینه حذف شود؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="حذف"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="7" icon="bi-receipt">هزینه‌ای ثبت نشده است.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($expenses->hasPages())
            <div class="card-footer">{{ $expenses->links() }}</div>
        @endif
    </div>
@endsection

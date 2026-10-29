@extends('layouts.admin')

@section('title', 'گروه‌های مخاطبان')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">گروه‌ها</li>
@endsection

@section('page-actions')
    <a href="{{ route('admin.groups.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> گروه جدید</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>گروه</th>
                            <th>نوع</th>
                            <th>تعداد اعضا</th>
                            <th>ایجاد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($groups as $group)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.groups.show', $group) }}" class="fw-semibold">{{ $group->name }}</a>
                                    @if ($group->description)
                                        <div class="small text-body-secondary">{{ \Illuminate\Support\Str::limit($group->description, 100) }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($group->isDynamic())
                                        <span class="badge text-bg-info">پویا</span>
                                    @else
                                        <span class="badge text-bg-light">دستی</span>
                                    @endif
                                </td>
                                <td>{{ number_format($group->member_count) }}</td>
                                <td class="small ltr text-nowrap">{{ \App\Support\Format::datetime($group->created_at, 'Y-m-d') }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="4" icon="bi-collection">هنوز گروهی ساخته نشده است.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <p class="small text-body-secondary mt-3 mb-0">
        گروه دستی: اعضا از صفحه هر مخاطب اضافه یا حذف می‌شوند.
        گروه پویا: اعضا هر بار بر اساس قواعد محاسبه می‌شوند و با تغییر داده‌ها خودکار وارد یا خارج می‌شوند.
    </p>
@endsection

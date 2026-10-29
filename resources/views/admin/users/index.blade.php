@extends('layouts.admin')

@section('title', 'کاربران پنل')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">کاربران</li>
@endsection

@section('page-actions')
    <a href="{{ route('admin.users.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> کاربر جدید
    </a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>ایمیل</th>
                            <th>نقش</th>
                            <th>تاریخ ایجاد</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $user)
                            <tr>
                                <td>
                                    {{ $user->name }}
                                    @if (auth()->user()->is($user))
                                        <span class="badge text-bg-light">شما</span>
                                    @endif
                                </td>
                                <td class="ltr">{{ $user->email }}</td>
                                <td>
                                    @if ($user->role)
                                        <span class="badge text-bg-primary">{{ $user->role->label() }}</span>
                                    @else
                                        <span class="badge text-bg-secondary">بدون دسترسی</span>
                                    @endif
                                </td>
                                <td class="ltr text-nowrap">{{ \App\Support\Format::datetime($user->created_at, 'Y-m-d') }}</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
                                        <i class="bi bi-pencil"></i> ویرایش
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if ($users->hasPages())
            <div class="card-footer">{{ $users->links() }}</div>
        @endif
    </div>

    <div class="card mt-3">
        <div class="card-header"><h2 class="card-title h6 mb-0">دسترسی نقش‌ها</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 text-center">
                    <thead>
                        <tr>
                            <th class="text-start">نقش</th>
                            @foreach (config('admin_permissions') as $label)
                                <th>{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (\App\Enums\AdminRole::cases() as $role)
                            <tr>
                                <td class="text-start">{{ $role->label() }}</td>
                                @foreach (array_keys(config('admin_permissions')) as $permission)
                                    <td>
                                        @if ($role->can($permission))
                                            <i class="bi bi-check-lg text-success" aria-label="دارد"></i>
                                        @else
                                            <i class="bi bi-dash text-body-tertiary" aria-label="ندارد"></i>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'ایده‌ها')

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
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>نام ایده</th>
                            <th>توضیحات</th>
                            <th>وضعیت</th>
                            <th>تاریخ ایجاد</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($ideas as $idea)
                            <tr>
                                <td class="fw-semibold">
                                    <a href="{{ route('ideas.show', $idea) }}">{{ $idea->name }}</a>
                                </td>
                                <td class="text-body-secondary">
                                    {{ \Illuminate\Support\Str::limit($idea->description, 120) ?: '—' }}
                                </td>
                                <td><x-admin.status-badge group="idea" :value="$idea->status" /></td>
                                <td class="ltr text-nowrap">{{ \App\Support\Format::datetime($idea->created_at, 'Y-m-d') }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="4" icon="bi-lightbulb">
                                هنوز ایده‌ای ایجاد نشده است.
                            </x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

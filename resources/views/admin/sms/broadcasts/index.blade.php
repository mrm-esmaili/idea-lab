@extends('layouts.admin')

@section('title', 'ارسال گروهی پیامک')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">ارسال گروهی</li>
@endsection

@section('page-actions')
    <a href="{{ route('admin.sms.broadcasts.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> ارسال جدید</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>عنوان</th>
                            <th>مخاطبان</th>
                            <th>گیرنده</th>
                            <th>وضعیت</th>
                            <th>ایجاد</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($broadcasts as $broadcast)
                            <tr>
                                <td><a href="{{ route('admin.sms.broadcasts.show', $broadcast) }}">{{ $broadcast->title }}</a></td>
                                <td>{{ $broadcast->audience === 'group' ? 'گروه: ' . ($broadcast->group?->name ?? 'حذف‌شده') : 'قواعد انتخاب' }}</td>
                                <td>{{ $broadcast->isDraft() ? '—' : number_format($broadcast->recipients_count) }}</td>
                                <td><x-admin.status-badge group="sms_broadcast" :value="$broadcast->status" /></td>
                                <td class="small">
                                    {{ $broadcast->creator?->name }}
                                    <div class="ltr text-body-secondary text-nowrap">{{ \App\Support\Format::datetime($broadcast->created_at) }}</div>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="5" icon="bi-megaphone">هنوز ارسال گروهی ثبت نشده است.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($broadcasts->hasPages())
            <div class="card-footer">{{ $broadcasts->links() }}</div>
        @endif
    </div>
@endsection

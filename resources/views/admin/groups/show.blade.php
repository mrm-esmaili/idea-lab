@extends('layouts.admin')

@section('title', $group->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.groups.index') }}">گروه‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $group->name }}</li>
@endsection

@section('page-actions')
    <div class="d-flex flex-wrap gap-2">
        @can('contacts.export')
            <div class="dropdown">
                <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download"></i> خروجی CSV
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="{{ route('admin.groups.export', $group) }}">همه اعضا</a></li>
                    <li><a class="dropdown-item" href="{{ route('admin.groups.export', [$group, 'consented_only' => 1]) }}">فقط دارای رضایت پیامک تبلیغاتی</a></li>
                </ul>
            </div>
        @endcan
        <a href="{{ route('admin.groups.edit', $group) }}" class="btn btn-outline-secondary"><i class="bi bi-pencil"></i> ویرایش</a>
        <form method="POST" action="{{ route('admin.groups.destroy', $group) }}" onsubmit="return confirm('گروه حذف شود؟ مخاطبان حذف نمی‌شوند.')">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> حذف</button>
        </form>
    </div>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            @if ($group->isDynamic())
                <span class="badge text-bg-info">پویا</span>
                <span class="small">{{ implode(' — ', $description) }}</span>
            @else
                <span class="badge text-bg-light">دستی</span>
                <span class="small text-body-secondary">اعضا از صفحه هر مخاطب اضافه یا حذف می‌شوند.</span>
            @endif
            @if ($group->description)
                <p class="small text-body-secondary mb-0 mt-2">{{ $group->description }}</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title h6 mb-0">{{ number_format($members->total()) }} عضو</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <tbody>
                        @forelse ($members as $contact)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.contacts.show', $contact) }}">{{ $contact->name ?: 'بدون نام' }}</a>
                                    <div class="small text-body-secondary ltr text-end">{{ $contact->mobile }}</div>
                                </td>
                                <td class="small">{{ $contact->interests->pluck('name')->implode('، ') ?: '—' }}</td>
                                <td>
                                    @if ($contact->marketing_sms_consent)
                                        <span class="badge text-bg-success">رضایت تبلیغاتی</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="3" icon="bi-people">این گروه عضوی ندارد.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($members->hasPages())
            <div class="card-footer">{{ $members->links() }}</div>
        @endif
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'مخاطبان')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">مخاطبان</li>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.contacts.index') }}">
                @include('admin.contacts._rules', ['prefix' => '', 'values' => $filters, 'withSearch' => true])

                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> فیلتر</button>
                    <a href="{{ route('admin.contacts.index') }}" class="btn btn-outline-secondary">پاک‌کردن</a>

                    @if (collect($filters)->except('q')->isNotEmpty())
                        <a href="{{ route('admin.groups.create', collect($filters)->except('q')->all()) }}" class="btn btn-outline-primary ms-auto">
                            <i class="bi bi-collection"></i> ذخیره به‌عنوان گروه پویا
                        </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h2 class="card-title h6 mb-0">
                {{ number_format($contacts->total()) }} مخاطب
                @if ($description)
                    <span class="small text-body-secondary fw-normal">— {{ implode('، ', $description) }}</span>
                @endif
            </h2>

            @can('contacts.export')
                <div class="dropdown ms-auto">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download"></i> خروجی CSV
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="{{ route('admin.contacts.export', $filters) }}">همه نتایج</a></li>
                        <li><a class="dropdown-item" href="{{ route('admin.contacts.export', $filters + ['consented_only' => 1]) }}">فقط دارای رضایت پیامک تبلیغاتی</a></li>
                    </ul>
                </div>
            @endcan
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>مخاطب</th>
                            <th>ایده‌های مورد علاقه</th>
                            <th>پیش‌خرید</th>
                            <th>رضایت تبلیغاتی</th>
                            <th>منبع جذب</th>
                            <th>ثبت</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($contacts as $contact)
                            <tr>
                                <td>
                                    <a href="{{ route('admin.contacts.show', $contact) }}" class="fw-semibold">{{ $contact->name ?: 'بدون نام' }}</a>
                                    <div class="small text-body-secondary ltr text-end">{{ $contact->mobile }}</div>
                                </td>
                                <td class="small">{{ $contact->interests->pluck('name')->implode('، ') ?: '—' }}</td>
                                <td>{{ number_format($contact->preorders_count) }}</td>
                                <td>
                                    @if ($contact->marketing_sms_consent)
                                        <span class="badge text-bg-success">دارد</span>
                                    @else
                                        <span class="badge text-bg-secondary">ندارد</span>
                                    @endif
                                </td>
                                <td class="small ltr text-end">{{ $contact->first_source ?? '—' }}{{ $contact->first_campaign ? ' / ' . $contact->first_campaign : '' }}</td>
                                <td class="small ltr text-nowrap">{{ \App\Support\Format::datetime($contact->created_at, 'Y-m-d') }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="6" icon="bi-person-vcard">مخاطبی با این شرایط پیدا نشد.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($contacts->hasPages())
            <div class="card-footer">{{ $contacts->links() }}</div>
        @endif
    </div>

    <p class="small text-body-secondary mt-3 mb-0">
        علاقه به ایده با ثبت فرم علاقه‌مندی، پیش‌خرید یا افزودن مدیر ثبت می‌شود؛ بازدید صفحه به‌تنهایی علاقه حساب نمی‌شود.
        هر خروجی با نام کاربر و تعداد ردیف ثبت می‌شود.
    </p>
@endsection

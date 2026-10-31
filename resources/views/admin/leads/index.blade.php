@extends('layouts.admin')

@section('title', 'سرنخ‌ها')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">سرنخ‌ها</li>
@endsection

@section('content')
    @php($statuses = config('statuses.lead'))

    <div class="card mb-3">
        <div class="card-body">
            <form
                method="GET"
                action="{{ route('admin.leads.index') }}"
                class="row g-2 align-items-end"
            >
                <div class="col-sm-6 col-lg-3">
                    <label for="q" class="form-label">جست‌وجوی نام یا شماره</label>
                    <input id="q" type="search" name="q" value="{{ request('q') }}" maxlength="100" class="form-control">
                </div>

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
                    <select name="status" id="status" class="form-select">
                        <option value="">همه وضعیت‌ها</option>
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="intent" class="form-label">نوع درخواست</label>
                    <select name="intent" id="intent" class="form-select">
                        <option value="">همه</option>
                        @foreach (\App\Models\Lead::INTENTS as $value => $label)
                            <option value="{{ $value }}" @selected(request('intent') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-sm-6 col-lg-3">
                    <label for="campaign" class="form-label">کمپین</label>
                    <input
                        id="campaign"
                        type="text"
                        name="campaign"
                        value="{{ request('campaign') }}"
                        placeholder="مثلاً clubyar01"
                        class="form-control"
                    >
                </div>

                <div class="col-auto">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-funnel"></i>
                        فیلتر
                    </button>
                    <a href="{{ route('admin.leads.index') }}" class="btn btn-outline-secondary">
                        پاک‌کردن فیلتر
                    </a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title h6 mb-0">
                تعداد نتایج: {{ number_format($leads->total()) }}
            </h2>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>شماره تماس</th>
                            <th>ایده / صفحه</th>
                            <th>منبع</th>
                            <th>کمپین</th>
                            <th>زمان ثبت</th>
                            <th>وضعیت</th>
                            <th class="table-actions">پیگیری</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($leads as $lead)
                            <tr>
                                <td>
                                    @if ($lead->contact && auth()->user()->can('contacts.manage'))
                                        <a href="{{ route('admin.contacts.show', $lead->contact) }}">{{ $lead->name }}</a>
                                    @else
                                        {{ $lead->name }}
                                    @endif
                                </td>
                                <td>
                                    <a href="tel:{{ $lead->phone }}" class="ltr">{{ $lead->phone }}</a>
                                </td>
                                <td>
                                    {{ $lead->landingPage->idea->name ?? '—' }}
                                    <div class="small text-body-secondary">
                                        {{ $lead->landingPage->name ?? '—' }}
                                        @if ($lead->intent === \App\Models\Lead::INTENT_NOTIFY)
                                            <span class="badge text-bg-info">خبرم کن</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="ltr">{{ $lead->source ?? '—' }}</td>
                                <td class="ltr">{{ $lead->campaign ?? '—' }}</td>
                                <td class="ltr text-nowrap">{{ \App\Support\Format::datetime($lead->created_at) }}</td>
                                <td><x-admin.status-badge group="lead" :value="$lead->status" /></td>
                                <td>
                                    <form
                                        method="POST"
                                        action="{{ route('admin.leads.update', $lead) }}"
                                        class="d-flex flex-column gap-1"
                                    >
                                        @csrf
                                        @method('PATCH')

                                        <select
                                            name="status"
                                            required
                                            class="form-select form-select-sm"
                                            aria-label="وضعیت سرنخ {{ $lead->name }}"
                                        >
                                            @foreach ($statuses as $value => $label)
                                                <option value="{{ $value }}" @selected($lead->status === $value)>
                                                    {{ $label }}
                                                </option>
                                            @endforeach
                                        </select>

                                        <textarea
                                            name="notes"
                                            rows="2"
                                            maxlength="5000"
                                            placeholder="یادداشت پیگیری"
                                            class="form-control form-control-sm"
                                            aria-label="یادداشت سرنخ {{ $lead->name }}"
                                        >{{ $lead->notes }}</textarea>

                                        <button type="submit" class="btn btn-sm btn-primary align-self-start">
                                            ذخیره
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="8">
                                سرنخی با این شرایط پیدا نشد.
                            </x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($leads->hasPages())
            <div class="card-footer">
                {{ $leads->links() }}
            </div>
        @endif
    </div>
@endsection

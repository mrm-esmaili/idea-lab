@extends('layouts.admin')

@section('title', 'کمپین‌ها')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">کمپین‌ها</li>
@endsection

@section('page-actions')
    <a href="{{ route('admin.campaigns.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> کمپین جدید
    </a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('admin.campaigns.index') }}" class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-3">
                    <label for="idea" class="form-label">ایده</label>
                    <select id="idea" name="idea" class="form-select">
                        <option value="">همه ایده‌ها</option>
                        @foreach ($ideas as $ideaOption)
                            <option value="{{ $ideaOption->id }}" @selected((string) request('idea') === (string) $ideaOption->id)>{{ $ideaOption->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="status" class="form-label">وضعیت</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">همه</option>
                        @foreach (config('statuses.campaign') as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> فیلتر</button>
                    <a href="{{ route('admin.campaigns.index') }}" class="btn btn-outline-secondary">پاک‌کردن</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>کمپین</th>
                            <th>ایده / صفحه</th>
                            <th>وضعیت</th>
                            <th>بازدیدکننده</th>
                            <th>سرنخ</th>
                            <th>پیش‌خرید</th>
                            <th>پرداخت</th>
                            <th>بودجه</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($campaigns as $campaign)
                            @php($s = $stats[$campaign->id])
                            <tr>
                                <td>
                                    <a href="{{ route('admin.campaigns.show', $campaign) }}" class="fw-semibold">{{ $campaign->name }}</a>
                                    <div class="small text-body-secondary ltr text-end">{{ $campaign->slug }}</div>
                                </td>
                                <td>
                                    {{ $campaign->landingPage->idea->name }}
                                    <div class="small text-body-secondary">{{ $campaign->landingPage->name }}</div>
                                </td>
                                <td><x-admin.status-badge group="campaign" :value="$campaign->status" /></td>
                                <td>{{ number_format($s['unique_visitors']) }}</td>
                                <td>
                                    {{ number_format($s['leads']) }}
                                    <div class="small text-body-secondary">{{ number_format($s['lead_rate'], 2) }}%</div>
                                </td>
                                <td>{{ number_format($s['preorders']) }}</td>
                                <td>{{ number_format($s['paid_count']) }}</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($campaign->budget_rial) }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="8" icon="bi-megaphone">کمپینی با این شرایط پیدا نشد.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($campaigns->hasPages())
            <div class="card-footer">{{ $campaigns->links() }}</div>
        @endif
    </div>

    @include('admin.campaigns._rule')
@endsection

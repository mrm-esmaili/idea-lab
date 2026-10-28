@extends('layouts.admin')

@section('title', $campaign->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.index') }}">کمپین‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $campaign->name }}</li>
@endsection

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('admin.campaigns.edit', $campaign) }}" class="btn btn-outline-secondary">
            <i class="bi bi-pencil"></i> ویرایش
        </a>
        <form
            method="POST"
            action="{{ route('admin.campaigns.destroy', $campaign) }}"
            onsubmit="return confirm('کمپین حذف شود؟ بازدیدها و سرنخ‌ها حذف نمی‌شوند.')"
        >
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i> حذف</button>
        </form>
    </div>
@endsection

@php($s = $campaignStats)

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <label for="tracking-url" class="form-label fw-semibold">لینک رهگیری برای تبلیغ</label>
            <div class="input-group ltr">
                <input id="tracking-url" type="text" readonly class="form-control" value="{{ $campaign->trackingUrl() }}">
                <button type="button" class="btn btn-outline-primary" id="copy-tracking-url">
                    <i class="bi bi-clipboard"></i> <span>کپی</span>
                </button>
            </div>
            <div class="form-text">
                <x-admin.status-badge group="campaign" :value="$campaign->status" />
                {{ $campaign->landingPage->idea->name }} / {{ $campaign->landingPage->name }}
                @if ($campaign->starts_on || $campaign->ends_on)
                    — از <span class="ltr">{{ $campaign->starts_on?->format('Y-m-d') ?? '…' }}</span>
                    تا <span class="ltr">{{ $campaign->ends_on?->format('Y-m-d') ?? '…' }}</span>
                @endif
            </div>
        </div>
    </div>

    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-5 g-3 mb-3">
        @foreach ([
            ['label' => 'بازدیدکننده یکتا', 'value' => number_format($s['unique_visitors']), 'hint' => number_format($s['page_views']) . ' بازدید — ' . number_format($s['cta_clicks']) . ' کلیک CTA'],
            ['label' => 'سرنخ', 'value' => number_format($s['leads']), 'hint' => 'نرخ ثبت: ' . number_format($s['lead_rate'], 2) . '%'],
            ['label' => 'پیش‌خرید', 'value' => number_format($s['preorders']), 'hint' => null],
            ['label' => 'پرداخت موفق', 'value' => number_format($s['paid_count']), 'hint' => \App\Support\Money::formatRialAsToman($s['paid_rial'])],
            ['label' => 'بودجه', 'value' => \App\Support\Money::formatRialAsToman($campaign->budget_rial), 'hint' => $s['cost_per_lead_rial'] !== null ? 'هر سرنخ: ' . \App\Support\Money::formatRialAsToman($s['cost_per_lead_rial']) : null],
        ] as $stat)
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="small text-body-secondary">{{ $stat['label'] }}</div>
                        <div class="fs-5 fw-bold">{{ $stat['value'] }}</div>
                        @if ($stat['hint'])
                            <div class="small text-body-secondary">{{ $stat['hint'] }}</div>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center">
            <h2 class="card-title h6 mb-0">آخرین سرنخ‌های این کمپین</h2>
            @can('leads.manage')
                <a href="{{ route('admin.leads.index', ['campaign' => $campaign->slug, 'idea' => $campaign->landingPage->idea_id]) }}" class="btn btn-sm btn-link ms-auto">همه</a>
            @endcan
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <tbody>
                        @forelse ($recentLeads as $lead)
                            <tr>
                                <td>{{ $lead->name }}</td>
                                <td class="ltr">{{ $lead->phone }}</td>
                                <td><x-admin.status-badge group="lead" :value="$lead->status" /></td>
                                <td class="ltr small text-nowrap">{{ \App\Support\Format::datetime($lead->created_at) }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="4">هنوز سرنخی از این کمپین ثبت نشده است.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if ($campaign->notes)
        <div class="card mt-3">
            <div class="card-body small">{!! nl2br(e($campaign->notes)) !!}</div>
        </div>
    @endif

    <p class="small text-body-secondary mt-3 mb-0">
        هزینه هر سرنخ بر اساس بودجه برنامه‌ریزی‌شده است؛ هزینه واقعی پس از ثبت هزینه‌ها (مرحله ۵۶) محاسبه می‌شود.
    </p>
    @include('admin.campaigns._rule')
@endsection

@push('scripts')
<script>
document.getElementById('copy-tracking-url')?.addEventListener('click', function () {
    const input = document.getElementById('tracking-url');
    const label = this.querySelector('span');
    const done = () => { label.textContent = 'کپی شد'; setTimeout(() => label.textContent = 'کپی', 2000); };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(input.value).then(done);
    } else {
        input.select();
        document.execCommand('copy');
        done();
    }
});
</script>
@endpush

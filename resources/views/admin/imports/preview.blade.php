@extends('layouts.admin')

@section('title', 'پیش‌نمایش ورود از فایل')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.imports.index') }}">ورود از فایل</a></li>
    <li class="breadcrumb-item active" aria-current="page">پیش‌نمایش</li>
@endsection

@section('content')
    @php($invalid = count($plans) - $valid)

    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge text-bg-primary fs-6">{{ number_format(count($plans)) }} ایده</span>
        <span class="badge text-bg-success fs-6">{{ number_format($valid) }} آماده</span>
        @if ($invalid)
            <span class="badge text-bg-danger fs-6">{{ number_format($invalid) }} دارای خطا</span>
        @endif
        <span class="badge text-bg-secondary fs-6">{{ number_format(collect($plans)->sum(fn ($p) => count($p['plan']['pages'] ?? []))) }} صفحه فرود</span>
        <span class="badge text-bg-secondary fs-6">{{ number_format(collect($plans)->sum(fn ($p) => count($p['plan']['campaigns'] ?? []))) }} کمپین</span>
    </div>

    @if ($file)
        <p class="small text-body-secondary">فایل: <bdi>{{ $file }}</bdi></p>
    @endif

    @foreach ($parseErrors as $error)
        <div class="alert alert-warning small">{{ $error }}</div>
    @endforeach

    @foreach ($plans as $plan)
        <div @class(['card mb-3', 'border-danger' => $plan['errors'] !== []])>
            <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                <h2 class="card-title h6 mb-0">{{ $loop->iteration }}. {{ $plan['name'] }}</h2>
                @if ($plan['errors'] !== [])
                    <span class="badge text-bg-danger">دارای خطا؛ ساخته نمی‌شود</span>
                @else
                    <span class="badge text-bg-success">آماده</span>
                @endif
            </div>
            <div class="card-body small">
                @if ($plan['errors'] !== [])
                    <ul class="text-danger mb-2">
                        @foreach ($plan['errors'] as $error)
                            <li><bdi>{{ $error }}</bdi></li>
                        @endforeach
                    </ul>
                @endif

                @if ($p = $plan['plan'])
                    <dl class="row mb-2">
                        <dt class="col-sm-3">نامک ایده</dt><dd class="col-sm-9"><bdi>{{ $p['idea']['slug'] }}</bdi></dd>
                        <dt class="col-sm-3">مبلغ پیش‌پرداخت</dt><dd class="col-sm-9">{{ $p['idea']['preorder_amount_rial'] ? \App\Support\Money::formatRialAsToman($p['idea']['preorder_amount_rial']) : '—' }}</dd>
                        <dt class="col-sm-3">شرایط بازپرداخت</dt><dd class="col-sm-9">{{ $p['refund_policy'] ? ($p['refund_policy']['is_refundable'] ? 'قابل بازپرداخت تا ' . $p['refund_policy']['refund_window_days'] . ' روز' : 'غیرقابل بازپرداخت') : '—' }}</dd>
                        @foreach ($p['pages'] as $page)
                            <dt class="col-sm-3">صفحه فرود</dt>
                            <dd class="col-sm-9">
                                {{ $page['title'] }} — <bdi dir="ltr">/l/{{ $page['slug'] }}</bdi>
                                <span class="text-body-secondary">({{ count($page['content']['features']) }} ویژگی، {{ count($page['content']['faq']) }} پرسش، {{ $page['is_active'] ? 'منتشرشده' : 'منتشرنشده' }})</span>
                            </dd>
                        @endforeach
                    </dl>

                    @if ($p['campaigns'])
                        <div class="table-responsive">
                            <table class="table table-sm mb-2">
                                <thead><tr><th>کمپین</th><th>کد</th><th>منبع / رسانه</th><th>بودجه</th><th>بازه</th></tr></thead>
                                <tbody>
                                    @foreach ($p['campaigns'] as $campaign)
                                        <tr>
                                            <td>{{ $campaign['name'] }}</td>
                                            <td><bdi dir="ltr">{{ $campaign['slug'] }}</bdi></td>
                                            <td><bdi dir="ltr">{{ $campaign['source'] ?? '—' }} / {{ $campaign['medium'] ?? '—' }}</bdi></td>
                                            <td class="text-nowrap">{{ $campaign['budget_rial'] !== null ? \App\Support\Money::formatRialAsToman($campaign['budget_rial']) : '—' }}</td>
                                            <td class="text-nowrap">{{ $campaign['starts_on'] ? \App\Support\Format::date(\Illuminate\Support\Carbon::parse($campaign['starts_on'])) : '—' }} تا {{ $campaign['ends_on'] ? \App\Support\Format::date(\Illuminate\Support\Carbon::parse($campaign['ends_on'])) : '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                @endif

                @if ($plan['warnings'] !== [])
                    <ul class="text-warning-emphasis mb-0">
                        @foreach ($plan['warnings'] as $warning)
                            <li><bdi>{{ $warning }}</bdi></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    @endforeach

    <div class="card">
        <div class="card-body">
            @if ($valid === 0)
                <p class="text-danger mb-2">هیچ ایده آماده‌ای نیست. خطاها را در فایل (یا با کمک هوش مصنوعی) رفع و دوباره بارگذاری کنید.</p>
                <a href="{{ route('admin.imports.index') }}" class="btn btn-outline-secondary">بارگذاری دوباره</a>
            @else
                <form method="POST" action="{{ route('admin.imports.confirm', $token) }}" data-confirm="{{ $valid }} ایده با صفحه فرود و کمپین‌ها ساخته شود؟">
                    @csrf
                    @if ($invalid)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="skip_invalid" value="1" id="skip_invalid" required>
                            <label class="form-check-label" for="skip_invalid">{{ number_format($invalid) }} ایده دارای خطا نادیده گرفته شود و فقط {{ number_format($valid) }} ایده آماده ساخته شود.</label>
                        </div>
                    @endif
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> ساخت {{ number_format($valid) }} ایده</button>
                    <a href="{{ route('admin.imports.index') }}" class="btn btn-outline-secondary">انصراف</a>
                    <div class="form-text">همه با هم ساخته می‌شوند؛ اگر هر مورد شکست بخورد، هیچ‌چیز ساخته نمی‌شود.</div>
                </form>
            @endif
        </div>
    </div>
@endsection

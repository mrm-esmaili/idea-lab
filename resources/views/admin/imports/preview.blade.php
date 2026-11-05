@extends('layouts.admin')

@section('title', 'پیش‌نمایش ورود از فایل')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.imports.index') }}">ورود از فایل</a></li>
    <li class="breadcrumb-item active" aria-current="page">پیش‌نمایش</li>
@endsection

@section('content')
    @php
        $invalid = count($plans) - $valid;
        $ready = collect($plans)->whereNotNull('plan');
        $updates = $ready->where('plan.mode', 'update')->count();
        $fieldNames = [
            'name' => 'نام', 'description' => 'توضیح', 'budget_rial' => 'بودجه', 'preorder_amount_rial' => 'مبلغ پیش‌پرداخت',
            'test_starts_on' => 'شروع آزمون', 'test_ends_on' => 'پایان آزمون', 'lead_goal' => 'هدف سرنخ', 'paid_preorder_goal' => 'هدف پیش‌پرداخت',
        ];
    @endphp

    <div class="d-flex flex-wrap gap-2 mb-3">
        <span class="badge text-bg-primary fs-6">{{ number_format(count($plans)) }} ایده</span>
        <span class="badge text-bg-success fs-6">{{ number_format($valid - $updates) }} جدید</span>
        @if ($updates)
            <span class="badge text-bg-info fs-6">{{ number_format($updates) }} به‌روزرسانی</span>
        @endif
        @if ($invalid)
            <span class="badge text-bg-danger fs-6">{{ number_format($invalid) }} دارای خطا</span>
        @endif
        <span class="badge text-bg-secondary fs-6">{{ number_format($ready->sum(fn ($p) => count($p['plan']['pages']))) }} صفحه فرود</span>
        <span class="badge text-bg-secondary fs-6">{{ number_format($ready->sum(fn ($p) => count($p['plan']['campaigns']))) }} کمپین</span>
    </div>

    @if ($file)
        <p class="small text-body-secondary">فایل: <bdi>{{ $file }}</bdi></p>
    @endif

    @foreach ($parseErrors as $error)
        <div class="alert alert-warning small">{{ $error }}</div>
    @endforeach

    @if (collect($plans)->contains(fn ($plan) => $plan['matches'] !== []))
        <div class="alert alert-info small">
            برخی ایده‌ها با ایده‌های موجود هم‌نام یا هم‌نامک‌اند. برای هر کدام انتخاب کنید: <strong>به‌روزرسانی</strong> (فقط فیلدهای موجود در فایل تغییر می‌کنند و تصویر، ویدیو، انتشار، وضعیت و نشانی صفحه حفظ می‌شود) یا <strong>ساخت ایده جدید</strong>.
        </div>
    @endif

    <form method="POST" action="{{ route('admin.imports.confirm', $token) }}" id="import-form" data-confirm="{{ $valid }} ایده ساخته یا به‌روزرسانی شود؟">
        @csrf

        @foreach ($plans as $plan)
            @php($p = $plan['plan'])
            <div @class(['card mb-3', 'border-danger' => $plan['errors'] !== [], 'border-info' => $p && $p['mode'] === 'update'])>
                <div class="card-header d-flex flex-wrap justify-content-between gap-2">
                    <h2 class="card-title h6 mb-0">{{ $loop->iteration }}. {{ $plan['name'] }}</h2>
                    @if ($plan['errors'] !== [])
                        <span class="badge text-bg-danger">دارای خطا؛ انجام نمی‌شود</span>
                    @elseif ($p['mode'] === 'update')
                        <span class="badge text-bg-info">به‌روزرسانی ایده موجود</span>
                    @else
                        <span class="badge text-bg-success">ایده جدید</span>
                    @endif
                </div>
                <div class="card-body small">
                    @if ($plan['matches'] !== [])
                        <fieldset class="mb-3 p-2 border rounded bg-body-tertiary">
                            <legend class="fs-6 fw-semibold mb-1">این ایده تکراری است؛ چه کنیم؟</legend>
                            @foreach ($plan['matches'] as $match)
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="decision[{{ $plan['index'] }}]" value="{{ $match['id'] }}" id="d-{{ $plan['index'] }}-{{ $match['id'] }}"
                                        @checked(($plan['target']['id'] ?? null) === $match['id']) data-refresh>
                                    <label class="form-check-label" for="d-{{ $plan['index'] }}-{{ $match['id'] }}">
                                        به‌روزرسانی «{{ $match['name'] }}» <span class="text-body-secondary">(هم{{ $match['by'] }}، <bdi dir="ltr">{{ $match['slug'] }}</bdi>، {{ $match['pages'] }} صفحه{{ $match['archived'] ? '، بایگانی‌شده' : '' }})</span>
                                    </label>
                                </div>
                            @endforeach
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision[{{ $plan['index'] }}]" value="new" id="d-{{ $plan['index'] }}-new"
                                    @checked($plan['target'] === null) data-refresh>
                                <label class="form-check-label" for="d-{{ $plan['index'] }}-new">ساخت ایده جدید (با نامک جدید)</label>
                            </div>
                        </fieldset>
                    @endif

                    @if ($plan['errors'] !== [])
                        <ul class="text-danger mb-2">
                            @foreach ($plan['errors'] as $error)
                                <li><bdi>{{ $error }}</bdi></li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($p)
                        <dl class="row mb-2">
                            <dt class="col-sm-3">نامک ایده</dt><dd class="col-sm-9"><bdi dir="ltr">{{ $p['slug'] }}</bdi></dd>
                            @if ($p['mode'] === 'update')
                                <dt class="col-sm-3">فیلدهای ایده که تغییر می‌کنند</dt>
                                <dd class="col-sm-9">{{ collect(array_keys($p['idea']))->map(fn ($f) => $fieldNames[$f] ?? $f)->join('، ') ?: '—' }}</dd>
                            @endif
                            @if (array_key_exists('preorder_amount_rial', $p['idea']))
                                <dt class="col-sm-3">مبلغ پیش‌پرداخت</dt><dd class="col-sm-9">{{ $p['idea']['preorder_amount_rial'] ? \App\Support\Money::formatRialAsToman($p['idea']['preorder_amount_rial']) : '—' }}</dd>
                            @endif
                            @if ($p['refund_policy'])
                                <dt class="col-sm-3">شرایط بازپرداخت</dt><dd class="col-sm-9">{{ $p['refund_policy']['is_refundable'] ? 'قابل بازپرداخت تا ' . $p['refund_policy']['refund_window_days'] . ' روز' : 'غیرقابل بازپرداخت' }}{{ $p['mode'] === 'update' ? ' (اگر با شرایط فعلی فرق داشته باشد، نسخه جدید ثبت می‌شود)' : '' }}</dd>
                            @endif
                            @foreach ($p['pages'] as $page)
                                <dt class="col-sm-3">صفحه فرود</dt>
                                <dd class="col-sm-9">
                                    @if ($page['existing_id'])
                                        <span class="badge text-bg-info">به‌روزرسانی</span>
                                    @else
                                        <span class="badge text-bg-success">جدید</span>
                                    @endif
                                    {{ $page['title'] }} — <bdi dir="ltr">/l/{{ $page['slug'] }}</bdi>
                                    <span class="text-body-secondary">({{ count($page['content']['features']) }} ویژگی، {{ count($page['content']['faq']) }} پرسش، {{ $page['is_active'] ? 'منتشرشده' : 'منتشرنشده' }})</span>
                                </dd>
                            @endforeach
                        </dl>

                        @if ($p['campaigns'])
                            <div class="table-responsive">
                                <table class="table table-sm mb-2">
                                    <thead><tr><th></th><th>کمپین</th><th>کد</th><th>منبع / رسانه</th><th>بودجه</th><th>بازه</th></tr></thead>
                                    <tbody>
                                        @foreach ($p['campaigns'] as $campaign)
                                            @php($a = $campaign['attributes'])
                                            <tr>
                                                <td>{!! $campaign['existing_id'] ? '<span class="badge text-bg-info">به‌روزرسانی</span>' : '<span class="badge text-bg-success">جدید</span>' !!}</td>
                                                <td>{{ $a['name'] ?? '—' }}</td>
                                                <td><bdi dir="ltr">{{ $campaign['code'] }}</bdi></td>
                                                <td><bdi dir="ltr">{{ $a['source'] ?? '—' }} / {{ $a['medium'] ?? '—' }}</bdi></td>
                                                <td class="text-nowrap">{{ isset($a['budget_rial']) ? \App\Support\Money::formatRialAsToman($a['budget_rial']) : '—' }}</td>
                                                <td class="text-nowrap">{{ ! empty($a['starts_on']) ? \App\Support\Format::date(\Illuminate\Support\Carbon::parse($a['starts_on'])) : '—' }} تا {{ ! empty($a['ends_on']) ? \App\Support\Format::date(\Illuminate\Support\Carbon::parse($a['ends_on'])) : '—' }}</td>
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
                    @if ($invalid)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="skip_invalid" value="1" id="skip_invalid" required>
                            <label class="form-check-label" for="skip_invalid">{{ number_format($invalid) }} ایده دارای خطا نادیده گرفته شود و فقط {{ number_format($valid) }} ایده آماده انجام شود.</label>
                        </div>
                    @endif
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle"></i> انجام: {{ number_format($valid - $updates) }} جدید، {{ number_format($updates) }} به‌روزرسانی</button>
                    @if (collect($plans)->contains(fn ($plan) => $plan['matches'] !== []))
                        <button type="submit" class="btn btn-outline-secondary" formaction="{{ route('admin.imports.preview', $token) }}" formmethod="GET" formnovalidate id="refresh-preview">بازبینی با انتخاب‌های جدید</button>
                    @endif
                    <a href="{{ route('admin.imports.index') }}" class="btn btn-link">انصراف</a>
                    <div class="form-text">همه با هم انجام می‌شوند؛ اگر هر مورد شکست بخورد، هیچ تغییری ذخیره نمی‌شود.</div>
                @endif
            </div>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        // تغییر انتخاب «به‌روزرسانی/جدید» پیش‌نمایش را با همان انتخاب‌ها تازه می‌کند
        document.querySelectorAll('[data-refresh]').forEach(function (radio) {
            radio.addEventListener('change', function () {
                const form = document.getElementById('import-form');
                const params = new URLSearchParams();
                form.querySelectorAll('input[type="radio"][name^="decision"]:checked').forEach(function (checked) {
                    params.append(checked.name, checked.value);
                });
                window.location = @json(route('admin.imports.preview', $token)) + '?' + params.toString();
            });
        });
    </script>
@endpush

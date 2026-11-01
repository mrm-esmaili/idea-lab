@extends('layouts.admin')

@section('title', 'قالب‌های پیامک')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">قالب‌های پیامک</li>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body small">
            <div class="mb-2">
                <strong>ارسال‌کننده فعلی:</strong>
                @if ($driver === 'smsir')
                    <span class="badge text-bg-success">SMS.ir</span>
                    @unless ($apiKeySet)
                        <span class="text-danger ms-2">کلید API در فایل <span class="ltr">.env</span> سرور (<span class="ltr">SMSIR_API_KEY</span>) تنظیم نشده است.</span>
                    @endunless
                @else
                    <span class="badge text-bg-warning">آزمایشی (لاگ)</span>
                    <span class="text-body-secondary ms-2">پیامک واقعی ارسال نمی‌شود. برای ارسال واقعی در فایل <span class="ltr">.env</span> سرور، <span class="ltr">SMS_DRIVER=smsir</span> و <span class="ltr">SMSIR_API_KEY</span> را تنظیم کنید.</span>
                @endif
            </div>
            <div class="mb-2">
                <strong>خط اختصاصی (ارسال گروهی و دستی):</strong>
                @if ($lineSet)
                    <span class="badge text-bg-success">تنظیم شده</span>
                @else
                    <span class="badge text-bg-secondary">تنظیم نشده</span>
                    <span class="text-body-secondary ms-2"><span class="ltr">SMSIR_LINE_NUMBER</span> در <span class="ltr">.env</span></span>
                @endif
            </div>
            <div>
                <strong>صف ارسال:</strong>
                {{ number_format($queue['queued']) }} پیام در صف —
                {{ number_format($queue['failed_24h']) }} ناموفق در ۲۴ ساعت اخیر —
                آخرین پردازش صف:
                <span class="ltr">{{ $queue['last_dispatch'] ? \App\Support\Format::datetime(\Illuminate\Support\Carbon::parse($queue['last_dispatch'])) : 'هرگز' }}</span>
                @can('sms.send')
                    — <a href="{{ route('admin.sms.messages.index') }}">سابقه پیامک</a>
                @endcan
            </div>
        </div>
    </div>

    @foreach (['otp', 'transactional', 'promotional'] as $category)
        @continue(! isset($templates[$category]))
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">قالب‌های {{ config("statuses.sms_category.{$category}") }}</h2></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>قالب</th>
                                <th>شناسه SMS.ir</th>
                                <th>وضعیت</th>
                                <th class="d-none d-lg-table-cell">متن</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($templates[$category] as $template)
                                <tr>
                                    <td>
                                        {{ $template->name }}
                                        <div class="small text-body-secondary">{{ $template->description }}</div>
                                    </td>
                                    <td>
                                        @if ($template->isConfigured())
                                            <span class="ltr">{{ $template->provider_template_id }}</span>
                                        @else
                                            <span class="badge text-bg-danger">تنظیم نشده</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($template->enabled)
                                            <span class="badge text-bg-success">فعال</span>
                                        @else
                                            <span class="badge text-bg-secondary">غیرفعال</span>
                                        @endif
                                    
                                        @if ($overLimit[$template->id] ?? [])
                                            <div class="small text-danger mt-1"><i class="bi bi-exclamation-triangle"></i> بلندتر از ۲۵ نویسه: {{ collect($overLimit[$template->id])->map(fn ($v) => config("statuses.sms_variable.{$v}", $v))->join('، ') }}</div>
                                        @endif
</td>
                                    <td class="small text-body-secondary d-none d-lg-table-cell" style="max-width: 320px">{{ \Illuminate\Support\Str::limit($template->body, 90) }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('admin.sms.templates.edit', $template) }}" class="btn btn-sm btn-outline-secondary text-nowrap">
                                            <i class="bi bi-pencil"></i> ویرایش
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endforeach

    <div class="alert alert-light border small">
        پیامک‌های تراکنشی (پرداخت، بازپرداخت، وضعیت ایده) از قالب‌های SMS.ir و پیامک‌های تبلیغاتی از
        @can('sms.broadcast')<a href="{{ route('admin.sms.broadcasts.index') }}">ارسال گروهی</a>@else ارسال گروهی @endcan
        با خط اختصاصی و فقط به مخاطبان دارای رضایت ارسال می‌شوند.
        برای ارسال منظم صف، در cPanel یک Cron Job هر دقیقه با فرمان
        <code class="ltr d-inline-block">php /home/USER/idea-lab/artisan schedule:run</code>
        بسازید.
    </div>
@endsection

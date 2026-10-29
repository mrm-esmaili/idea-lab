@extends('layouts.admin')

@section('title', 'پیامک')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">پیامک</li>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body small">
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
    </div>

    <div class="card mb-3">
        <div class="card-header"><h2 class="card-title h6 mb-0">قالب‌ها</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>قالب</th>
                            <th>نوع</th>
                            <th>شناسه قالب SMS.ir</th>
                            <th>پارامترها</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($templates as $template)
                            <tr>
                                <td>
                                    {{ $template->name }}
                                    <div class="small text-body-secondary ltr text-end">{{ $template->key }}</div>
                                </td>
                                <td>{{ config("statuses.sms_category.{$template->category}", $template->category) }}</td>
                                <td>
                                    @if ($template->isConfigured())
                                        <span class="ltr">{{ $template->provider_template_id }}</span>
                                    @else
                                        <span class="badge text-bg-danger">تنظیم نشده</span>
                                    @endif
                                </td>
                                <td class="small ltr text-end">
                                    @foreach ($template->parameters as $variable => $name)
                                        <div>{{ $name }}</div>
                                    @endforeach
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('admin.sms.templates.edit', $template) }}" class="btn btn-sm btn-outline-secondary">
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

    <div class="card">
        <div class="card-header"><h2 class="card-title h6 mb-0">آخرین ارسال‌ها</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>گیرنده</th>
                            <th>قالب</th>
                            <th>ارسال‌کننده</th>
                            <th>وضعیت</th>
                            <th>شناسه پیام</th>
                            <th>زمان</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($messages as $message)
                            <tr>
                                <td class="ltr">{{ \App\Support\PhoneNumber::mask($message->mobile) }}</td>
                                <td class="ltr">{{ $message->template_key }}</td>
                                <td>{{ $message->provider }}</td>
                                <td>
                                    @if ($message->status === 'sent')
                                        <span class="badge text-bg-success">ارسال شد</span>
                                    @else
                                        <span class="badge text-bg-danger">ناموفق</span>
                                        <div class="small text-body-secondary">{{ $message->error }}</div>
                                    @endif
                                </td>
                                <td class="ltr small">{{ $message->provider_message_id ?? '—' }}</td>
                                <td class="ltr small text-nowrap">{{ \App\Support\Format::datetime($message->created_at) }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="6" icon="bi-chat-dots">هنوز پیامکی ارسال نشده است.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

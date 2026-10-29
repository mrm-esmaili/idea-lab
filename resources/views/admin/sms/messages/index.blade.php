@extends('layouts.admin')

@section('title', 'سابقه پیامک')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">سابقه پیامک</li>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2 align-items-end">
                <div class="col-sm-6 col-lg-2">
                    <label for="mobile" class="form-label">موبایل</label>
                    <input id="mobile" name="mobile" value="{{ $filters['mobile'] ?? '' }}" maxlength="20" class="form-control ltr">
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="status" class="form-label">وضعیت</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">همه</option>
                        @foreach (config('statuses.sms_message') as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['status'] ?? null) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label for="category" class="form-label">نوع</label>
                    <select id="category" name="category" class="form-select">
                        <option value="">همه</option>
                        @foreach (config('statuses.sms_category') as $key => $label)
                            <option value="{{ $key }}" @selected(($filters['category'] ?? null) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="template" class="form-label">قالب</label>
                    <select id="template" name="template" class="form-select">
                        <option value="">همه</option>
                        @foreach ($templateKeys as $key => $name)
                            <option value="{{ $key }}" @selected(($filters['template'] ?? null) === $key)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
                @if (! empty($filters['broadcast']))
                    <input type="hidden" name="broadcast" value="{{ $filters['broadcast'] }}">
                @endif
                <div class="col-lg-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> اعمال</button>
                    <a href="{{ route('admin.sms.messages.index') }}" class="btn btn-outline-secondary">همه</a>
                </div>
            </form>
        </div>
        <div class="card-footer small d-flex flex-wrap gap-3">
            @foreach (config('statuses.sms_message') as $key => $label)
                @continue(! isset($summary[$key]))
                <span>{{ $label }}: <strong>{{ number_format($summary[$key]->total) }}</strong></span>
            @endforeach
            <span class="text-body-secondary">هزینه گزارش‌شده: {{ number_format((float) $summary->sum('cost')) }}</span>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>گیرنده</th>
                            <th>پیام</th>
                            <th>وضعیت</th>
                            <th>ارائه‌دهنده</th>
                            <th>زمان</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($messages as $message)
                            <tr>
                                <td>
                                    <span class="ltr">{{ \App\Support\PhoneNumber::mask($message->mobile) }}</span>
                                    @if ($message->contact)
                                        <div class="small"><a href="{{ route('admin.contacts.show', $message->contact_id) }}">{{ $message->contact->name ?: 'پرونده مخاطب' }}</a></div>
                                    @endif
                                </td>
                                <td class="small" style="max-width: 340px">
                                    <div>
                                        <span class="badge text-bg-light">{{ config("statuses.sms_category.{$message->category}", $message->category) }}</span>
                                        @if ($message->template_key)
                                            {{ $templateKeys[$message->template_key] ?? $message->template_key }}
                                        @elseif ($message->broadcast)
                                            <a href="{{ route('admin.sms.broadcasts.show', $message->broadcast_id) }}">{{ $message->broadcast->title }}</a>
                                        @else
                                            پیام دستی{{ $message->user ? ' — ' . $message->user->name : '' }}
                                        @endif
                                    </div>
                                    @if ($message->type === 'text')
                                        <div class="text-body-secondary">{{ \Illuminate\Support\Str::limit($message->body, 80) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <x-admin.status-badge group="sms_message" :value="$message->status" />
                                    @if ($message->attempts > 1)
                                        <span class="small text-body-secondary">({{ $message->attempts }} تلاش)</span>
                                    @endif
                                    @if ($message->error)
                                        <div class="small text-body-secondary">{{ $message->error }}</div>
                                    @endif
                                </td>
                                <td class="small">
                                    {{ $message->provider }}
                                    @if ($message->provider_message_id)
                                        <div class="ltr text-end">{{ $message->provider_message_id }}</div>
                                    @endif
                                    @if ($message->response || $message->parameters)
                                        <details>
                                            <summary>جزئیات</summary>
                                            <pre class="ltr text-start small mb-0" style="white-space: pre-wrap; max-width: 260px">{{ json_encode(['parameters' => $message->parameters, 'response' => $message->response], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) }}</pre>
                                        </details>
                                    @endif
                                </td>
                                <td class="ltr small text-nowrap">
                                    {{ \App\Support\Format::datetime($message->created_at) }}
                                    @if ($message->sent_at)
                                        <div class="text-body-secondary">{{ \App\Support\Format::datetime($message->sent_at) }}</div>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @if ($message->status === 'failed' && $message->category === 'transactional')
                                        <form method="POST" action="{{ route('admin.sms.messages.retry', $message) }}" data-confirm="اگر نتیجه ارسال قبلی نامعلوم است، ممکن است پیام تکراری برسد. ادامه می‌دهید؟">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-arrow-repeat"></i> ارسال دوباره</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="6" icon="bi-chat-dots">پیامکی پیدا نشد.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($messages->hasPages())
            <div class="card-footer">{{ $messages->links() }}</div>
        @endif
    </div>
@endsection

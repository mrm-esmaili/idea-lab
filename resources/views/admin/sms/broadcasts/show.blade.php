@extends('layouts.admin')

@section('title', 'ارسال گروهی: ' . $broadcast->title)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.sms.broadcasts.index') }}">ارسال گروهی</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $broadcast->title }}</li>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h2 class="card-title h6 mb-0">پیام</h2>
                    <x-admin.status-badge group="sms_broadcast" :value="$broadcast->status" />
                </div>
                <div class="card-body">
                    <div class="border rounded p-3 bg-body-tertiary mb-2" style="white-space: pre-line">{{ $text }}</div>
                    <div class="small text-body-secondary">{{ mb_strlen($text) }} نویسه — {{ $parts }} بخش پیامک برای هر گیرنده</div>

                    <dl class="row small mt-3 mb-0">
                        <dt class="col-5">مخاطبان</dt>
                        <dd class="col-7">
                            @if ($broadcast->audience === 'group')
                                گروه:
                                @if ($broadcast->group)
                                    <a href="{{ route('admin.groups.show', $broadcast->group) }}">{{ $broadcast->group->name }}</a>
                                @else
                                    حذف‌شده
                                @endif
                            @else
                                @forelse ($rulesDescription as $line)
                                    <div>{{ $line }}</div>
                                @empty
                                    همه مخاطبان
                                @endforelse
                            @endif
                            <div class="text-body-secondary">+ فقط دارندگان رضایت پیامک تبلیغاتی</div>
                        </dd>
                        <dt class="col-5">حداقل فاصله</dt>
                        <dd class="col-7">{{ $broadcast->frequency_cap_hours ? $broadcast->frequency_cap_hours . ' ساعت از آخرین پیامک تبلیغاتی' : 'ندارد' }}</dd>
                        <dt class="col-5">ایجاد</dt>
                        <dd class="col-7">{{ $broadcast->creator?->name }} — <span class="ltr">{{ \App\Support\Format::datetime($broadcast->created_at) }}</span></dd>
                        @if ($broadcast->confirmed_at)
                            <dt class="col-5">تأیید ارسال</dt>
                            <dd class="col-7">{{ $broadcast->confirmer?->name }} — <span class="ltr">{{ \App\Support\Format::datetime($broadcast->confirmed_at) }}</span></dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            @if ($preview)
                <div class="card mb-3 border-primary">
                    <div class="card-header"><h2 class="card-title h6 mb-0">پیش‌نمایش گیرندگان و تأیید نهایی</h2></div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between"><span>مخاطبان منطبق</span><span>{{ number_format($preview['matched']) }}</span></li>
                        <li class="list-group-item d-flex justify-content-between text-body-secondary"><span>− بدون رضایت تبلیغاتی</span><span>{{ number_format($preview['no_consent']) }}</span></li>
                        <li class="list-group-item d-flex justify-content-between text-body-secondary"><span>− پیامک تبلیغاتی اخیر</span><span>{{ number_format($preview['recently_messaged']) }}</span></li>
                        <li class="list-group-item d-flex justify-content-between fw-bold"><span>گیرنده نهایی</span><span>{{ number_format($preview['eligible']) }} نفر ({{ number_format($preview['eligible'] * $parts) }} بخش پیامک)</span></li>
                    </ul>
                    <div class="card-body">
                        @unless ($lineSet)
                            <div class="alert alert-warning small">خط اختصاصی SMS.ir (<span class="ltr">SMSIR_LINE_NUMBER</span>) تنظیم نشده و ارسال ممکن نیست.</div>
                        @endunless
                        @unless ($promoWindowOpen)
                            <div class="alert alert-light border small">اکنون خارج از ساعات مجاز پیامک تبلیغاتی است؛ پیام‌ها در صف می‌مانند و در اولین ساعت مجاز ارسال می‌شوند.</div>
                        @endunless

                        @if ($preview['eligible'] > 0)
                            <form method="POST" action="{{ route('admin.sms.broadcasts.confirm', $broadcast) }}">
                                @csrf
                                <input type="hidden" name="expected" value="{{ $preview['eligible'] }}">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="confirm" value="1" id="confirm" required>
                                    <label class="form-check-label" for="confirm">
                                        متن و تعداد گیرندگان را بررسی کردم؛ پیامک برای {{ number_format($preview['eligible']) }} نفر ارسال شود.
                                    </label>
                                </div>
                                <button type="submit" class="btn btn-primary"><i class="bi bi-send"></i> تأیید و ارسال</button>
                            </form>
                        @else
                            <p class="mb-0 text-body-secondary">گیرنده واجد شرایطی وجود ندارد.</p>
                        @endif
                    </div>
                    <div class="card-footer d-flex gap-2">
                        <form method="POST" action="{{ route('admin.sms.broadcasts.destroy', $broadcast) }}" data-confirm="پیش‌نویس حذف شود؟" data-confirm-variant="danger">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">حذف پیش‌نویس</button>
                        </form>
                        <a href="{{ route('admin.sms.broadcasts.create', $broadcast->audience === 'group' ? ['group' => $broadcast->contact_group_id] : ($broadcast->rules ?? [])) }}" class="btn btn-sm btn-outline-secondary">ساخت پیش‌نویس با همین مخاطبان</a>
                    </div>
                </div>
            @else
                <div class="card mb-3">
                    <div class="card-header"><h2 class="card-title h6 mb-0">پیشرفت ارسال</h2></div>
                    <ul class="list-group list-group-flush">
                        @foreach (config('statuses.sms_message') as $status => $label)
                            @continue(! isset($progress[$status]))
                            <li class="list-group-item d-flex justify-content-between">
                                <span><x-admin.status-badge group="sms_message" :value="$status" /></span>
                                <a href="{{ route('admin.sms.messages.index', ['broadcast' => $broadcast->id, 'status' => $status]) }}">{{ number_format($progress[$status]->total) }}</a>
                            </li>
                        @endforeach
                        <li class="list-group-item d-flex justify-content-between small text-body-secondary">
                            <span>هزینه گزارش‌شده ارائه‌دهنده</span>
                            <span>{{ number_format((float) $progress->sum('cost')) }}</span>
                        </li>
                    </ul>
                    @if ($broadcast->status === 'sending')
                        <div class="card-body small">
                            پیام‌ها دسته‌ای و با محدودیت نرخ ارسال می‌شوند. آخرین پردازش صف:
                            <span class="ltr">{{ $lastDispatch ? \App\Support\Format::datetime(\Illuminate\Support\Carbon::parse($lastDispatch)) : 'هرگز' }}</span>.
                            اگر Cron روی هاست تنظیم نشده، با دکمه زیر دسته بعدی را ارسال کنید.
                            @unless ($promoWindowOpen)
                                <div class="text-warning-emphasis mt-1">اکنون خارج از ساعات مجاز پیامک تبلیغاتی است.</div>
                            @endunless
                        </div>
                        <div class="card-footer d-flex flex-wrap gap-2">
                            <form method="POST" action="{{ route('admin.sms.broadcasts.dispatch', $broadcast) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-play"></i> ارسال دسته بعدی</button>
                            </form>
                            <form method="POST" action="{{ route('admin.sms.broadcasts.cancel', $broadcast) }}" data-confirm="ارسال پیام‌های باقی‌مانده لغو شود؟" data-confirm-variant="danger">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger">لغو باقی‌مانده</button>
                            </form>
                        </div>
                    @endif
                </div>

                @if ($failures->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header"><h2 class="card-title h6 mb-0">آخرین خطاها</h2></div>
                        <ul class="list-group list-group-flush small">
                            @foreach ($failures as $failure)
                                <li class="list-group-item">
                                    <span class="ltr">{{ \App\Support\PhoneNumber::mask($failure->mobile) }}</span> — {{ $failure->error }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            @endif
        </div>
    </div>
@endsection

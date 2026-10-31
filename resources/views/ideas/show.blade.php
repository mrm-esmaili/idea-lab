@extends('layouts.admin')

@section('title', $idea->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $idea->name }}</li>
@endsection

@section('page-actions')
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('ideas.edit', $idea) }}" class="btn btn-outline-secondary">
            <i class="bi bi-pencil"></i> ویرایش
        </a>

        <a href="{{ route('admin.experiments.index', ['idea' => $idea->id]) }}" class="btn btn-outline-secondary">
            <i class="bi bi-clipboard-data"></i> آزمایش‌ها
        </a>

        <a href="{{ route('ideas.settings', $idea) }}" class="btn btn-outline-secondary">
            <i class="bi bi-sliders"></i> تنظیمات
        </a>

        @if ($idea->isArchived())
            <form method="POST" action="{{ route('ideas.restore', $idea) }}">
                @csrf
                <button type="submit" class="btn btn-outline-success">
                    <i class="bi bi-arrow-counterclockwise"></i> خروج از بایگانی
                </button>
            </form>
        @else
            <form
                method="POST"
                action="{{ route('ideas.archive', $idea) }}"
                data-confirm="با بایگانی، صفحات فرود این ایده برای عموم غیرقابل‌دسترس می‌شوند. ادامه می‌دهید؟" data-confirm-variant="danger"
            >
                @csrf
                <button type="submit" class="btn btn-outline-warning">
                    <i class="bi bi-archive"></i> بایگانی
                </button>
            </form>
        @endif

        @if ($idea->landingPages->isEmpty())
            <form
                method="POST"
                action="{{ route('ideas.destroy', $idea) }}"
                data-confirm="این ایده برای همیشه حذف می‌شود. مطمئن هستید؟" data-confirm-variant="danger"
            >
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger">
                    <i class="bi bi-trash"></i> حذف
                </button>
            </form>
        @endif

        <a href="{{ route('landing-pages.create', $idea) }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> ساخت صفحه فرود
        </a>
    </div>
@endsection

@section('content')
    @if ($idea->isArchived())
        <div class="alert alert-warning">
            <i class="bi bi-archive me-1"></i>
            این ایده در {{ \App\Support\Format::datetime($idea->archived_at) }} بایگانی شده و صفحات فرود آن عمومی نیستند.
        </div>
    @endif

    <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-4 g-3 mb-3">
        @foreach ([
            ['label' => 'بازدیدکننده یکتا', 'value' => number_format($totals['unique_visitors']), 'hint' => number_format($totals['page_views']) . ' بازدید صفحه', 'icon' => 'bi-people', 'color' => 'primary'],
            ['label' => 'سرنخ', 'value' => number_format($totals['leads']), 'hint' => 'نرخ ثبت: ' . number_format($totals['lead_rate'], 2) . '% — ' . number_format($totals['notify_requests']) . ' درخواست «خبرم کن»', 'icon' => 'bi-person-lines-fill', 'color' => 'success'],
            ['label' => 'درخواست پیش‌خرید', 'value' => number_format($totals['preorders']), 'hint' => null, 'icon' => 'bi-bag', 'color' => 'info'],
            ['label' => 'پرداخت موفق', 'value' => number_format($totals['paid_count']), 'hint' => \App\Support\Money::formatRialAsToman($totals['paid_rial']), 'icon' => 'bi-credit-card', 'color' => 'warning'],
        ] as $stat)
            <div class="col">
                <div class="info-box h-100">
                    <span class="info-box-icon text-bg-{{ $stat['color'] }} shadow-sm"><i class="bi {{ $stat['icon'] }}"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ $stat['label'] }}</span>
                        <span class="info-box-number">{{ $stat['value'] }}</span>
                        @if ($stat['hint'])
                            <span class="small text-body-secondary">{{ $stat['hint'] }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header">
                    <h2 class="card-title h5 mb-0">صفحات فرود</h2>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>صفحه</th>
                                    <th>وضعیت</th>
                                    <th title="بازدیدکننده یکتا">بازدیدکننده</th>
                                    <th>سرنخ</th>
                                    <th>پیش‌خرید</th>
                                    <th>پرداخت</th>
                                    <th>عملیات</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse ($idea->landingPages as $landingPage)
                                    @php($s = $pageStats[$landingPage->id])
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $landingPage->name }}</div>
                                            <div class="small text-body-secondary ltr text-end">/l/{{ $landingPage->slug }}</div>
                                        </td>
                                        <td>
                                            @if ($landingPage->is_active)
                                                <span class="badge text-bg-success">منتشرشده</span>
                                            @else
                                                <span class="badge text-bg-secondary">غیرفعال</span>
                                            @endif
                                        </td>
                                        <td>
                                            {{ number_format($s['unique_visitors']) }}
                                            @if ($s['demo_clicks'] || $s['video_plays'])
                                                <div class="small text-body-secondary">{{ number_format($s['demo_clicks']) }} دمو، {{ number_format($s['video_plays']) }} پخش ویدیو</div>
                                            @endif
                                        </td>
                                        <td>
                                            {{ number_format($s['leads']) }}
                                            <div class="small text-body-secondary">{{ number_format($s['lead_rate'], 2) }}%</div>
                                        </td>
                                        <td>
                                            {{ number_format($s['preorders']) }}
                                            @if ($s['notify_requests'])
                                                <div class="small text-body-secondary">{{ number_format($s['notify_requests']) }} خبرم کن</div>
                                            @endif
                                        </td>
                                        <td>{{ number_format($s['paid_count']) }}</td>
                                        <td class="text-nowrap">
                                            <a href="{{ route('landing-pages.edit', $landingPage) }}" class="btn btn-sm btn-outline-secondary" title="ویرایش" aria-label="ویرایش {{ $landingPage->name }}">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <a href="{{ $landingPage->is_active ? route('landing-pages.show', $landingPage->slug) : route('landing-pages.preview', $landingPage) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary" title="{{ $landingPage->is_active ? 'مشاهده' : 'پیش‌نمایش' }}" aria-label="{{ $landingPage->is_active ? 'مشاهده' : 'پیش‌نمایش' }} {{ $landingPage->name }}">
                                                <i class="bi bi-box-arrow-up-left"></i>
                                            </a>
                                            <form
                                                method="POST"
                                                action="{{ route('landing-pages.toggle', $landingPage) }}"
                                                class="d-inline"
                                                @if ($landingPage->is_active)
                                                    data-confirm="صفحه غیرفعال شود؟ لینک‌های تبلیغاتی این صفحه از کار می‌افتند." data-confirm-variant="danger"
                                                @endif
                                            >
                                                @csrf
                                                @method('PATCH')
                                                @if ($landingPage->is_active)
                                                    <button type="submit" class="btn btn-sm btn-outline-warning">غیرفعال‌سازی</button>
                                                @else
                                                    <button type="submit" class="btn btn-sm btn-outline-success">انتشار</button>
                                                @endif
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="7" icon="bi-window">
                                        هنوز صفحه فرودی برای این ایده ساخته نشده است.
                                    </x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer small text-body-secondary">
                    نرخ ثبت = سرنخ تقسیم بر بازدیدکننده یکتای همان صفحه. بازدیدکننده یکتا بر اساس کوکی مرورگر شمرده می‌شود.
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h2 class="card-title h5 mb-0">کمپین‌ها</h2>
                    <a href="{{ route('admin.campaigns.create', ['landing_page' => $idea->landingPages->first()?->id]) }}" class="btn btn-sm btn-link ms-auto">
                        <i class="bi bi-plus-lg"></i> کمپین جدید
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 align-middle">
                            <thead>
                                <tr>
                                    <th>کمپین</th>
                                    <th>وضعیت</th>
                                    <th>بازدیدکننده</th>
                                    <th>سرنخ</th>
                                    <th>پیش‌خرید</th>
                                    <th>پرداخت</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($campaigns as $campaign)
                                    @php($cs = $campaignReport['campaigns'][$campaign->id])
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.campaigns.show', $campaign) }}">{{ $campaign->name }}</a>
                                            <div class="small text-body-secondary">{{ $campaign->landingPage->name }}</div>
                                        </td>
                                        <td><x-admin.status-badge group="campaign" :value="$campaign->status" /></td>
                                        <td>{{ number_format($cs['unique_visitors']) }}</td>
                                        <td>{{ number_format($cs['leads']) }} <span class="small text-body-secondary">({{ number_format($cs['lead_rate'], 2) }}%)</span></td>
                                        <td>{{ number_format($cs['preorders']) }}</td>
                                        <td>{{ number_format($cs['paid_count']) }}</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="6" icon="bi-megaphone">هنوز کمپینی برای صفحات این ایده ثبت نشده است.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($campaignReport['other']->isNotEmpty())
                    <div class="card-footer">
                        <div class="small fw-semibold mb-2">ترافیک بدون کمپین ثبت‌شده</div>
                        <div class="table-responsive">
                            <table class="table table-sm mb-0 small">
                                <tbody>
                                    @foreach ($campaignReport['other'] as $row)
                                        <tr>
                                            <td>
                                                @if ($row['campaign'])
                                                    <span class="ltr">{{ $row['campaign'] }}</span>
                                                    <span class="badge text-bg-warning">کد ثبت‌نشده</span>
                                                @else
                                                    <span class="text-body-secondary">بدون utm_campaign (مستقیم/ارگانیک)</span>
                                                @endif
                                                <div class="text-body-secondary">{{ $idea->landingPages->firstWhere('id', $row['landing_page_id'])?->name }}</div>
                                            </td>
                                            <td>{{ number_format($row['unique_visitors']) }} بازدیدکننده</td>
                                            <td>{{ number_format($row['leads']) }} سرنخ</td>
                                            <td>{{ number_format($row['preorders']) }} پیش‌خرید</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h2 class="card-title h5 mb-0">آخرین سرنخ‌ها</h2>
                    @can('leads.manage')
                        <a href="{{ route('admin.leads.index', ['idea' => $idea->id]) }}" class="btn btn-sm btn-link ms-auto">همه سرنخ‌های این ایده</a>
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
                                        <td class="small text-body-secondary">{{ $lead->landingPage->name }}</td>
                                        <td><x-admin.status-badge group="lead" :value="$lead->status" /></td>
                                        <td class="ltr text-nowrap small">{{ \App\Support\Format::datetime($lead->created_at) }}</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="5">هنوز سرنخی ثبت نشده است.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            @if ($idea->description)
                <div class="card mb-3">
                    <div class="card-header"><h2 class="card-title h5 mb-0">توضیحات</h2></div>
                    <div class="card-body">{!! nl2br(e($idea->description)) !!}</div>
                </div>
            @endif
        </div>

        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header d-flex align-items-center">
                    <h2 class="card-title h5 mb-0">تنظیمات و اهداف</h2>
                    <a href="{{ route('ideas.settings', $idea) }}" class="btn btn-sm btn-link ms-auto">ویرایش</a>
                </div>
                <div class="card-body">
                    @foreach ([
                        ['label' => 'سرنخ', 'value' => $totals['leads'], 'goal' => $idea->lead_goal],
                        ['label' => 'پیش‌خرید پرداخت‌شده', 'value' => $totals['paid_count'], 'goal' => $idea->paid_preorder_goal],
                    ] as $goal)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between small">
                                <span>{{ $goal['label'] }}</span>
                                <span>
                                    {{ number_format($goal['value']) }}
                                    @if ($goal['goal'])
                                        از {{ number_format($goal['goal']) }}
                                    @else
                                        <span class="text-body-secondary">(حدنصاب تعیین نشده)</span>
                                    @endif
                                </span>
                            </div>
                            @if ($goal['goal'])
                                @php($percent = min(100, (int) round($goal['value'] / $goal['goal'] * 100)))
                                <div
                                    class="progress mt-1" role="progressbar" style="height: 8px"
                                    aria-label="پیشرفت {{ $goal['label'] }}"
                                    aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"
                                >
                                    <div @class(['progress-bar', 'bg-success' => $percent >= 100]) style="width: {{ $percent }}%"></div>
                                </div>
                            @endif
                        </div>
                    @endforeach

                    <dl class="row small mb-0">
                        <dt class="col-5 fw-normal text-body-secondary">بودجه</dt>
                        <dd class="col-7">{{ \App\Support\Money::formatRialAsToman($idea->budget_rial) }}</dd>

                        <dt class="col-5 fw-normal text-body-secondary">بازه آزمایش</dt>
                        <dd class="col-7">
                            @if ($idea->test_starts_on || $idea->test_ends_on)
                                از <span class="ltr text-nowrap">{{ $idea->test_starts_on ? \App\Support\Format::date($idea->test_starts_on) : '…' }}</span>
                                تا <span class="ltr text-nowrap">{{ $idea->test_ends_on ? \App\Support\Format::date($idea->test_ends_on) : '…' }}</span>
                            @else
                                —
                            @endif
                        </dd>

                        <dt class="col-5 fw-normal text-body-secondary">مبلغ پیش‌پرداخت</dt>
                        <dd class="col-7">{{ \App\Support\Money::formatRialAsToman($idea->preorder_amount_rial) }}</dd>

                        <dt class="col-5 fw-normal text-body-secondary">شرایط بازپرداخت</dt>
                        <dd class="col-7">
                            @if ($idea->currentRefundPolicy)
                                <a href="{{ route('ideas.refund-policy', $idea) }}">
                                    نسخه {{ $idea->currentRefundPolicy->version }}
                                </a>
                                — {{ $idea->currentRefundPolicy->summary() }}
                            @else
                                <a href="{{ route('ideas.refund-policy', $idea) }}">تعیین نشده</a>
                            @endif
                        </dd>
                    </dl>

                    @if ($blockers = $idea->preorderBlockers())
                        <div class="alert alert-warning small mb-0 mt-3">
                            <strong>پیش‌خرید بسته است:</strong>
                            {{ implode(' ', $blockers) }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h5 mb-0">وضعیت ایده</h2></div>
                <div class="card-body">
                    <p>
                        وضعیت فعلی:
                        <x-admin.status-badge group="idea" :value="$idea->status" class="fs-6" />
                    </p>

                    @php($transitions = $idea->statusEnum()->allowedTransitions())

                    <form method="POST" action="{{ route('ideas.status', $idea) }}" data-confirm="وضعیت ایده به «{status}» تغییر کند؟">
                        @csrf
                        @method('PATCH')

                        <div class="mb-2">
                            <label for="status" class="form-label">تغییر به</label>
                            <select id="status" name="status" required @class(['form-select', 'is-invalid' => $errors->has('status')])>
                                @foreach ($transitions as $status)
                                    <option value="{{ $status->value }}" @selected(old('status') === $status->value)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                            @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="mb-2">
                            <label for="note" class="form-label">دلیل یا توضیح</label>
                            <textarea id="note" name="note" rows="2" maxlength="2000" class="form-control">{{ old('note') }}</textarea>
                        </div>

                        @php($smsStatuses = collect(\App\Services\Notifications\CustomerNotifier::IDEA_STATUS_TEMPLATES)->filter(fn ($key) => $statusSms['templates'][$key] ?? false)->keys())
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" value="1" id="notify_customers" name="notify_customers" @checked(old('notify_customers')) @disabled($smsStatuses->isEmpty() || ! $statusSms['buyers'])>
                            <label class="form-check-label small" for="notify_customers">
                                اطلاع پیامکی به {{ number_format($statusSms['buyers']) }} خریدار پیش‌خرید
                            </label>
                        </div>
                        <div class="form-text mb-2">
                            @if ($smsStatuses->isEmpty())
                                قالب پیامک وضعیت‌ها (تأییدشده، ردشده، عرضه‌شده) هنوز فعال نشده است.
                            @else
                                فقط برای: {{ $smsStatuses->map(fn ($s) => \App\Enums\IdeaStatus::from($s)->label())->join('، ') }}.
                            @endif
                            پیام به علاقه‌مندانِ بدون خرید، تبلیغاتی است و از
                            @can('sms.broadcast')
                                <a href="{{ route('admin.sms.broadcasts.create', ['ideas' => [$idea->id]]) }}">ارسال گروهی</a>
                            @else
                                ارسال گروهی
                            @endcan
                            (فقط دارندگان رضایت) انجام می‌شود.
                        </div>

                        @if (collect($transitions)->contains(\App\Enums\IdeaStatus::Launched))
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" value="1" id="notify_requesters" name="notify_requesters" @checked(old('notify_requesters')) @disabled(! $statusSms['requesters_template'] || ! $statusSms['requesters'])>
                                <label class="form-check-label small" for="notify_requesters">
                                    هنگام «عرضه‌شده»: پیامک آمادگی به {{ number_format($statusSms['requesters']) }} نفر که «خبرم کن» زده‌اند
                                </label>
                            </div>
                            <div class="form-text mb-2">
                                @if (! $statusSms['requesters_template'])
                                    قالب «آماده‌شدن محصول (درخواست‌کنندگان)» در قالب‌های پیامک فعال نیست.
                                @else
                                    پیام خدماتی است (خودشان درخواست کرده‌اند)؛ هر شماره فقط یک بار و خریداران جدا پیام می‌گیرند.
                                @endif
                            </div>
                        @endif

                        <button type="submit" class="btn btn-primary btn-sm">ثبت تغییر وضعیت</button>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h5 mb-0">تاریخچه وضعیت</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($idea->statusChanges as $change)
                        <li class="list-group-item">
                            <div>
                                <x-admin.status-badge group="idea" :value="$change->from_status" />
                                <i class="bi bi-arrow-left mx-1"></i>
                                <x-admin.status-badge group="idea" :value="$change->to_status" />
                            </div>
                            @if ($change->note)
                                <div class="small mt-1">{{ $change->note }}</div>
                            @endif
                            <div class="small text-body-secondary mt-1">
                                {{ $change->user?->name ?? 'کاربر حذف‌شده' }}
                                — <span class="ltr">{{ \App\Support\Format::datetime($change->created_at) }}</span>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-body-secondary small">هنوز تغییری ثبت نشده است.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.admin')

@section('title', $contact->name ?: $contact->mobile)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.contacts.index') }}">مخاطبان</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $contact->name ?: $contact->mobile }}</li>
@endsection

@php($sources = ['lead_form' => 'فرم علاقه‌مندی', 'preorder' => 'پیش‌خرید', 'admin' => 'ثبت مدیر'])

@section('content')
    <div class="row g-3">
        <div class="col-xl-4">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">اطلاعات مخاطب</h2></div>
                <div class="card-body">
                    <dl class="row small mb-0">
                        <dt class="col-5 fw-normal text-body-secondary">موبایل</dt>
                        <dd class="col-7"><a href="tel:{{ $contact->mobile }}" class="ltr">{{ $contact->mobile }}</a></dd>

                        <dt class="col-5 fw-normal text-body-secondary">نام</dt>
                        <dd class="col-7">{{ $contact->name ?: '—' }}</dd>

                        <dt class="col-5 fw-normal text-body-secondary">ایمیل</dt>
                        <dd class="col-7 ltr text-end">{{ $contact->email ?: '—' }}</dd>

                        <dt class="col-5 fw-normal text-body-secondary">اولین تماس</dt>
                        <dd class="col-7">
                            {{ $contact->firstLandingPage?->idea?->name ?? '—' }}
                            @if ($contact->firstLandingPage)
                                <div class="text-body-secondary">{{ $contact->firstLandingPage->name }}</div>
                            @endif
                            @if ($contact->first_source || $contact->first_campaign)
                                <div class="ltr text-end text-body-secondary">{{ $contact->first_source ?? '—' }} / {{ $contact->first_campaign ?? '—' }}</div>
                            @endif
                        </dd>

                        <dt class="col-5 fw-normal text-body-secondary">رضایت تبلیغاتی</dt>
                        <dd class="col-7">
                            @if ($contact->marketing_sms_consent)
                                <span class="badge text-bg-success">دارد</span>
                                <div class="text-body-secondary">
                                    از <span class="ltr">{{ \App\Support\Format::datetime($contact->marketing_consent_at, 'Y-m-d') }}</span>
                                    — {{ $contact->marketing_consent_source }}
                                </div>
                            @else
                                <span class="badge text-bg-secondary">ندارد</span>
                                @if ($contact->marketing_opted_out_at)
                                    <div class="text-body-secondary">لغو در <span class="ltr">{{ \App\Support\Format::datetime($contact->marketing_opted_out_at, 'Y-m-d') }}</span></div>
                                @endif
                            @endif
                        </dd>

                        <dt class="col-5 fw-normal text-body-secondary">ثبت / آخرین ورود</dt>
                        <dd class="col-7 ltr text-end">
                            {{ \App\Support\Format::datetime($contact->created_at, 'Y-m-d') }}
                            / {{ \App\Support\Format::datetime($contact->last_login_at, 'Y-m-d') }}
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">علاقه به ایده‌ها</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($contact->interests as $idea)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <div class="flex-grow-1">
                                <a href="{{ route('ideas.show', $idea) }}">{{ $idea->name }}</a>
                                <div class="small text-body-secondary">
                                    {{ $sources[$idea->pivot->source] ?? $idea->pivot->source }}
                                    @if ($idea->pivot->campaign) — <span class="ltr">{{ $idea->pivot->campaign }}</span> @endif
                                </div>
                            </div>
                            <form method="POST" action="{{ route('admin.contacts.interests.destroy', [$contact, $idea]) }}"
                                data-confirm="علاقه به این ایده حذف شود؟" data-confirm-variant="danger">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0" aria-label="حذف علاقه به {{ $idea->name }}"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </li>
                    @empty
                        <li class="list-group-item small text-body-secondary">علاقه‌ای ثبت نشده است.</li>
                    @endforelse
                </ul>
                @php($availableIdeas = $ideas->whereNotIn('id', $contact->interests->pluck('id')))
                @if ($availableIdeas->isNotEmpty())
                <div class="card-footer">
                    <form method="POST" action="{{ route('admin.contacts.interests.store', $contact) }}" class="d-flex gap-2">
                        @csrf
                        <select name="idea_id" class="form-select form-select-sm" aria-label="ایده" required>
                            @foreach ($availableIdeas as $ideaOption)
                                <option value="{{ $ideaOption->id }}">{{ $ideaOption->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">افزودن</button>
                    </form>
                </div>
                @endif
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">گروه‌ها</h2></div>
                <ul class="list-group list-group-flush">
                    @foreach ($contact->groups as $group)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <a href="{{ route('admin.groups.show', $group) }}" class="flex-grow-1">{{ $group->name }}</a>
                            <span class="badge text-bg-light">دستی</span>
                            <form method="POST" action="{{ route('admin.contacts.groups.destroy', [$contact, $group]) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-link text-danger p-0" aria-label="خروج از {{ $group->name }}"><i class="bi bi-x-lg"></i></button>
                            </form>
                        </li>
                    @endforeach
                    @foreach ($dynamicGroups as $group)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <a href="{{ route('admin.groups.show', $group) }}" class="flex-grow-1">{{ $group->name }}</a>
                            <span class="badge text-bg-info">پویا</span>
                        </li>
                    @endforeach
                    @if ($contact->groups->isEmpty() && $dynamicGroups->isEmpty())
                        <li class="list-group-item small text-body-secondary">عضو گروهی نیست.</li>
                    @endif
                </ul>
                @php($availableGroups = $manualGroups->whereNotIn('id', $contact->groups->pluck('id')))
                @if ($availableGroups->isNotEmpty())
                    <div class="card-footer">
                        <form method="POST" action="{{ route('admin.contacts.groups.store', $contact) }}" class="d-flex gap-2">
                            @csrf
                            <select name="contact_group_id" class="form-select form-select-sm" aria-label="گروه دستی" required>
                                @foreach ($availableGroups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary text-nowrap">افزودن به گروه</button>
                        </form>
                    </div>
                @endif
            </div>

            @can('sms.send')
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="card-title h6 mb-0">ارسال پیامک</h2>
                        <a href="{{ route('admin.sms.messages.index', ['mobile' => $contact->mobile]) }}" class="small">سابقه پیامک</a>
                    </div>
                    <form method="POST" action="{{ route('admin.contacts.sms', $contact) }}" data-confirm="این پیامک به {{ $contact->mobile }} ارسال شود؟&#10;&#10;{body}">
                        @csrf
                        <div class="card-body">
                            <label for="sms-body" class="form-label small">پیام خدماتی به این مخاطب (پیگیری سفارش، پاسخ پشتیبانی)</label>
                            <textarea id="sms-body" name="body" rows="3" maxlength="300" required @class(['form-control', 'is-invalid' => $errors->has('body') || $errors->has('sms_body')])>{{ old('body') }}</textarea>
                            @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            @error('sms_body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <div class="form-text">پیام تبلیغاتی فقط از «ارسال گروهی» و به دارندگان رضایت ارسال می‌شود.</div>
                        </div>
                        <div class="card-footer">
                            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-send"></i> ارسال</button>
                        </div>
                    </form>
                </div>
            @endcan
        </div>

        <div class="col-xl-8">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">پیش‌خریدها</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <tbody>
                                @forelse ($contact->preorders as $preorder)
                                    <tr>
                                        <td><a href="{{ route('admin.preorders.show', $preorder) }}">{{ $preorder->landingPage?->idea?->name }}</a></td>
                                        <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($preorder->amount_rial) }}</td>
                                        <td><x-admin.status-badge group="preorder_payment" :value="$preorder->payment_status" /></td>
                                        <td><x-admin.status-badge group="preorder" :value="$preorder->status" /></td>
                                        <td class="ltr small text-nowrap">{{ \App\Support\Format::datetime($preorder->created_at, 'Y-m-d') }}</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="5">پیش‌خریدی ندارد.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">سرنخ‌ها</h2></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0 align-middle">
                            <tbody>
                                @forelse ($contact->leads as $lead)
                                    <tr>
                                        <td>{{ $lead->landingPage?->idea?->name }}<div class="small text-body-secondary">{{ $lead->landingPage?->name }}</div></td>
                                        <td><x-admin.status-badge group="lead" :value="$lead->status" /></td>
                                        <td class="small">{{ \Illuminate\Support\Str::limit($lead->notes, 80) ?: '—' }}</td>
                                        <td class="ltr small text-nowrap">{{ \App\Support\Format::datetime($lead->created_at, 'Y-m-d') }}</td>
                                    </tr>
                                @empty
                                    <x-admin.empty-row colspan="4">سرنخی ندارد.</x-admin.empty-row>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title h6 mb-0">تاریخچه تعاملات</h2></div>
                <ul class="list-group list-group-flush">
                    @forelse ($timeline as $item)
                        <li class="list-group-item d-flex gap-3">
                            <i class="bi {{ $item['icon'] }} text-body-secondary fs-5"></i>
                            <div class="flex-grow-1">
                                @if ($item['url'])
                                    <a href="{{ $item['url'] }}">{{ $item['title'] }}</a>
                                @else
                                    {{ $item['title'] }}
                                @endif
                                @if ($item['detail'])
                                    <div class="small text-body-secondary">{{ $item['detail'] }}</div>
                                @endif
                            </div>
                            <span class="small text-body-secondary ltr text-nowrap">{{ \App\Support\Format::datetime($item['at']) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item small text-body-secondary">تعاملی ثبت نشده است.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
@endsection

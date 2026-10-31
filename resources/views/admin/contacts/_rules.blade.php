{{-- فیلدهای قواعد انتخاب مخاطب؛ $prefix خالی برای فیلتر فهرست، «rules» برای گروه پویا --}}
@php
    $field = fn (string $key) => $prefix ? "{$prefix}[{$key}]" : $key;
    $fieldId = fn (string $key) => ($prefix ? "{$prefix}-" : 'filter-') . $key;
    $value = fn (string $key) => old($prefix ? "{$prefix}.{$key}" : $key, $values[$key] ?? null);
    $selectedIdeas = array_map('intval', (array) $value('ideas'));
@endphp

<div class="row g-2">
    @if ($withSearch ?? false)
        <div class="col-sm-6 col-lg-3">
            <label for="{{ $fieldId('q') }}" class="form-label">جست‌وجوی نام یا موبایل</label>
            <input id="{{ $fieldId('q') }}" type="search" name="{{ $field('q') }}" value="{{ $value('q') }}" maxlength="100" class="form-control">
        </div>
    @endif

    <div class="col-sm-6 col-lg-3">
        <label for="{{ $fieldId('ideas') }}" class="form-label">علاقه‌مند به ایده</label>
        <select id="{{ $fieldId('ideas') }}" name="{{ $field('ideas') }}[]" class="form-select" multiple size="3">
            @foreach ($ideas as $ideaOption)
                <option value="{{ $ideaOption->id }}" @selected(in_array($ideaOption->id, $selectedIdeas, true))>{{ $ideaOption->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-sm-6 col-lg-2">
        <label for="{{ $fieldId('payment') }}" class="form-label">پیش‌خرید</label>
        <select id="{{ $fieldId('payment') }}" name="{{ $field('payment') }}" class="form-select">
            <option value="">همه</option>
            @foreach (['paid' => 'پرداخت‌شده', 'refunded' => 'کامل بازپرداخت‌شده', 'unpaid' => 'پرداخت‌نشده', 'none' => 'ندارد'] as $key => $label)
                <option value="{{ $key }}" @selected($value('payment') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-sm-6 col-lg-2">
        <label for="{{ $fieldId('consent') }}" class="form-label">رضایت پیامک تبلیغاتی</label>
        <select id="{{ $fieldId('consent') }}" name="{{ $field('consent') }}" class="form-select">
            <option value="">همه</option>
            <option value="yes" @selected($value('consent') === 'yes')>دارد</option>
            <option value="no" @selected($value('consent') === 'no')>ندارد</option>
        </select>
    </div>

    <div class="col-sm-6 col-lg-2">
        <label for="{{ $fieldId('lead_status') }}" class="form-label">وضعیت سرنخ</label>
        <select id="{{ $fieldId('lead_status') }}" name="{{ $field('lead_status') }}" class="form-select">
            <option value="">همه</option>
            @foreach (config('statuses.lead') as $key => $label)
                <option value="{{ $key }}" @selected($value('lead_status') === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-sm-6 col-lg-2">
        <label for="{{ $fieldId('notify_idea') }}" class="form-label">«خبرم کن» برای ایده</label>
        <select id="{{ $fieldId('notify_idea') }}" name="{{ $field('notify_idea') }}" class="form-select">
            <option value="">همه</option>
            @foreach ($ideas as $ideaOption)
                <option value="{{ $ideaOption->id }}" @selected((int) $value('notify_idea') === $ideaOption->id)>{{ $ideaOption->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-sm-6 col-lg-2">
        <label for="{{ $fieldId('campaign') }}" class="form-label">کد کمپین</label>
        <input id="{{ $fieldId('campaign') }}" type="text" name="{{ $field('campaign') }}" value="{{ $value('campaign') }}" maxlength="100" class="form-control ltr">
    </div>

    <div class="col-sm-6 col-lg-2">
        <label for="{{ $fieldId('source') }}" class="form-label">منبع جذب</label>
        <input id="{{ $fieldId('source') }}" type="text" name="{{ $field('source') }}" value="{{ $value('source') }}" maxlength="100" class="form-control ltr" placeholder="divar">
    </div>

    <div class="col-sm-6 col-lg-2">
        <label for="{{ $fieldId('joined_within_days') }}" class="form-label">ثبت‌شده در چند روز اخیر</label>
        <input id="{{ $fieldId('joined_within_days') }}" type="number" min="1" max="3650" name="{{ $field('joined_within_days') }}" value="{{ $value('joined_within_days') }}" class="form-control">
    </div>
</div>

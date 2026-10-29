@php
    $criteriaRows = old('criteria', collect($experiment->criteria ?? [])->map(fn ($c) => [
        'metric' => $c['metric'],
        'operator' => $c['operator'],
        'target' => ($metricDefs[$c['metric']][1] ?? null) === 'rial' ? \App\Support\Money::rialToToman((int) $c['target']) : $c['target'] + 0,
        'kind' => $c['kind'],
    ])->all());
    $criteriaRows = array_values(array_pad($criteriaRows, max(5, count($criteriaRows)), []));
    $selected = array_map('intval', old('campaign_ids', $selectedCampaigns));
@endphp

<div class="card mb-3">
    <div class="card-header"><h2 class="card-title h6 mb-0">تعریف آزمایش</h2></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label for="idea_id" class="form-label">ایده</label>
                <select id="idea_id" name="idea_id" required @class(['form-select', 'is-invalid' => $errors->has('idea_id')])>
                    <option value="">—</option>
                    @foreach ($ideas as $idea)
                        <option value="{{ $idea->id }}" @selected((int) old('idea_id', $experiment->idea_id) === $idea->id)>{{ $idea->name }}</option>
                    @endforeach
                </select>
                @error('idea_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="title" class="form-label">عنوان</label>
                <input id="title" name="title" maxlength="150" required value="{{ old('title', $experiment->title) }}" @class(['form-control', 'is-invalid' => $errors->has('title')])>
                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="hypothesis" class="form-label">فرضیه</label>
                <textarea id="hypothesis" name="hypothesis" rows="3" required maxlength="5000" placeholder="مثلاً: مدیران باشگاه حاضرند برای نرم‌افزار نوبت‌دهی پیش‌پرداخت کنند." @class(['form-control', 'is-invalid' => $errors->has('hypothesis')])>{{ old('hypothesis', $experiment->hypothesis) }}</textarea>
                @error('hypothesis')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
                <label for="goal" class="form-label">هدف (چه چیزی باید روشن شود)</label>
                <textarea id="goal" name="goal" rows="3" required maxlength="5000" @class(['form-control', 'is-invalid' => $errors->has('goal')])>{{ old('goal', $experiment->goal) }}</textarea>
                @error('goal')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="starts_on" class="form-label">شروع</label>
                <input id="starts_on" type="date" name="starts_on" required value="{{ old('starts_on', $experiment->starts_on?->toDateString()) }}" @class(['form-control', 'is-invalid' => $errors->has('starts_on')])>
                @error('starts_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="ends_on" class="form-label">پایان</label>
                <input id="ends_on" type="date" name="ends_on" required value="{{ old('ends_on', $experiment->ends_on?->toDateString()) }}" @class(['form-control', 'is-invalid' => $errors->has('ends_on')])>
                @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="budget_toman" class="form-label">بودجه (تومان)</label>
                <input id="budget_toman" name="budget_toman" inputmode="numeric" value="{{ old('budget_toman', $experiment->budget_rial ? \App\Support\Money::rialToToman($experiment->budget_rial) : '') }}" @class(['form-control', 'ltr', 'is-invalid' => $errors->has('budget_toman')])>
                @error('budget_toman')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="min_sample" class="form-label">حداقل بازدیدکننده یکتا</label>
                <input id="min_sample" type="number" min="0" name="min_sample" required value="{{ old('min_sample', $experiment->min_sample ?? 0) }}" @class(['form-control', 'is-invalid' => $errors->has('min_sample')])>
                <div class="form-text">کمتر از این، نتیجه «داده ناکافی» است.</div>
                @error('min_sample')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-12">
                <label for="campaign_ids" class="form-label">کمپین‌ها (اختیاری)</label>
                <select id="campaign_ids" name="campaign_ids[]" multiple size="4" @class(['form-select', 'is-invalid' => $errors->has('campaign_ids')])>
                    @foreach ($campaigns as $campaign)
                        <option value="{{ $campaign->id }}" data-idea="{{ $campaign->landingPage?->idea_id }}" @selected(in_array($campaign->id, $selected, true))>
                            {{ $campaign->name }} ({{ $campaign->slug }})
                        </option>
                    @endforeach
                </select>
                <div class="form-text">بدون انتخاب، همه ترافیک صفحات ایده در بازه سنجیده می‌شود. کمپین باید متعلق به همین ایده باشد.</div>
                @error('campaign_ids')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header"><h2 class="card-title h6 mb-0">معیارهای موفقیت و توقف</h2></div>
    <div class="card-body">
        <p class="small text-body-secondary">
            «موفقیت» یعنی همه معیارهای موفقیت برقرار باشند؛ «توقف» یعنی برقرار شدن هر یک از معیارهای توقف.
            مبالغ به تومان و نرخ‌ها به درصد. ردیف خالی نادیده گرفته می‌شود. ارزیابی سیستم پیشنهاد است و تصمیم نهایی با مدیر.
        </p>
        @error('criteria')<div class="alert alert-danger small">{{ $message }}</div>@enderror
        @foreach ($criteriaRows as $i => $row)
            <div class="row g-2 mb-2 align-items-center">
                <div class="col-6 col-lg-2">
                    <select name="criteria[{{ $i }}][kind]" class="form-select form-select-sm" aria-label="نوع معیار {{ $i + 1 }}">
                        <option value="success" @selected(($row['kind'] ?? 'success') === 'success')>موفقیت</option>
                        <option value="stop" @selected(($row['kind'] ?? null) === 'stop')>توقف</option>
                    </select>
                </div>
                <div class="col-6 col-lg-4">
                    <select name="criteria[{{ $i }}][metric]" class="form-select form-select-sm" aria-label="شاخص معیار {{ $i + 1 }}">
                        <option value="">— شاخص —</option>
                        @foreach ($metricDefs as $key => [$label, $unit])
                            <option value="{{ $key }}" @selected(($row['metric'] ?? null) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <select name="criteria[{{ $i }}][operator]" class="form-select form-select-sm" aria-label="مقایسه معیار {{ $i + 1 }}">
                        <option value="gte" @selected(($row['operator'] ?? 'gte') === 'gte')>حداقل</option>
                        <option value="lte" @selected(($row['operator'] ?? null) === 'lte')>حداکثر</option>
                    </select>
                </div>
                <div class="col-6 col-lg-3">
                    <input name="criteria[{{ $i }}][target]" value="{{ $row['target'] ?? '' }}" inputmode="decimal" aria-label="مقدار معیار {{ $i + 1 }}" @class(['form-control', 'form-control-sm', 'ltr', 'is-invalid' => $errors->has("criteria.{$i}.target") || $errors->has("criteria.{$i}.metric")])>
                </div>
            </div>
        @endforeach
    </div>
</div>

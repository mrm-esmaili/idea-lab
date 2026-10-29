@csrf

<div class="card-body">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="landing_page_id" class="form-label">صفحه فرود <span class="text-danger">*</span></label>
            <select id="landing_page_id" name="landing_page_id" required @class(['form-select', 'is-invalid' => $errors->has('landing_page_id')])>
                <option value="">انتخاب کنید</option>
                @foreach ($pages as $ideaName => $ideaPages)
                    <optgroup label="{{ $ideaName }}">
                        @foreach ($ideaPages as $page)
                            <option value="{{ $page->id }}" @selected((string) old('landing_page_id', $campaign->landing_page_id) === (string) $page->id)>
                                {{ $page->name }} (/l/{{ $page->slug }})
                            </option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            @error('landing_page_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label for="name" class="form-label">نام کمپین <span class="text-danger">*</span></label>
            <input id="name" type="text" name="name" maxlength="255" required
                value="{{ old('name', $campaign->name) }}" placeholder="مثلاً آگهی دیوار — آبان"
                @class(['form-control', 'is-invalid' => $errors->has('name')])>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-4">
            <label for="slug" class="form-label">کد کمپین (utm_campaign) <span class="text-danger">*</span></label>
            <input id="slug" type="text" name="slug" maxlength="100" required
                value="{{ old('slug', $campaign->slug) }}" placeholder="clubyar-divar-aban"
                @class(['form-control', 'ltr', 'is-invalid' => $errors->has('slug')])>
            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @if ($campaign->exists)
                <div class="form-text text-warning-emphasis">تغییر کد یا صفحه، انتساب داده‌های قبلی را تغییر می‌دهد.</div>
            @endif
        </div>

        <div class="col-md-4">
            <label for="source" class="form-label">منبع (utm_source)</label>
            <input id="source" type="text" name="source" maxlength="100"
                value="{{ old('source', $campaign->source) }}" placeholder="divar"
                @class(['form-control', 'ltr', 'is-invalid' => $errors->has('source')])>
            @error('source')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-4">
            <label for="medium" class="form-label">رسانه (utm_medium)</label>
            <input id="medium" type="text" name="medium" maxlength="100"
                value="{{ old('medium', $campaign->medium) }}" placeholder="paid"
                @class(['form-control', 'ltr', 'is-invalid' => $errors->has('medium')])>
            @error('medium')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label for="status" class="form-label">وضعیت</label>
            <select id="status" name="status" @class(['form-select', 'is-invalid' => $errors->has('status')])>
                @foreach (config('statuses.campaign') as $value => $label)
                    <option value="{{ $value }}" @selected(old('status', $campaign->status) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-3">
            <label for="starts_on" class="form-label">شروع</label>
            <input id="starts_on" type="date" name="starts_on" value="{{ old('starts_on', $campaign->starts_on?->format('Y-m-d')) }}"
                @class(['form-control', 'is-invalid' => $errors->has('starts_on')])>
        </div>

        <div class="col-md-3">
            <label for="ends_on" class="form-label">پایان</label>
            <input id="ends_on" type="date" name="ends_on" value="{{ old('ends_on', $campaign->ends_on?->format('Y-m-d')) }}"
                @class(['form-control', 'is-invalid' => $errors->has('ends_on')])>
            @error('ends_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-3">
            <label for="budget_toman" class="form-label">بودجه (تومان)</label>
            <input id="budget_toman" type="text" inputmode="numeric" name="budget_toman"
                value="{{ old('budget_toman', $campaign->budget_rial !== null ? number_format(\App\Support\Money::rialToToman($campaign->budget_rial)) : '') }}"
                @class(['form-control', 'ltr', 'is-invalid' => $errors->has('budget_toman')])>
            @error('budget_toman')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label for="notes" class="form-label">یادداشت</label>
            <textarea id="notes" name="notes" rows="2" maxlength="2000" class="form-control">{{ old('notes', $campaign->notes) }}</textarea>
        </div>
    </div>
</div>

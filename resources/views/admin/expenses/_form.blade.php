@csrf

<div class="card-body">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="idea_id" class="form-label">ایده</label>
            <select id="idea_id" name="idea_id" @class(['form-select', 'is-invalid' => $errors->has('idea_id')])>
                <option value="">عمومی (بدون ایده)</option>
                @foreach ($ideas as $ideaOption)
                    <option value="{{ $ideaOption->id }}" @selected((string) old('idea_id', $expense->idea_id) === (string) $ideaOption->id)>{{ $ideaOption->name }}</option>
                @endforeach
            </select>
            @error('idea_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label for="campaign_id" class="form-label">کمپین (اختیاری)</label>
            <select id="campaign_id" name="campaign_id" @class(['form-select', 'is-invalid' => $errors->has('campaign_id')])>
                <option value="">—</option>
                @foreach ($campaigns as $campaign)
                    <option value="{{ $campaign->id }}" @selected((string) old('campaign_id', $expense->campaign_id) === (string) $campaign->id)>{{ $campaign->name }} ({{ $campaign->slug }})</option>
                @endforeach
            </select>
            @error('campaign_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">هزینه کمپین در هزینه هر سرنخ همان کمپین حساب می‌شود.</div>
        </div>

        <div class="col-md-4">
            <label for="category" class="form-label">دسته</label>
            <select id="category" name="category" class="form-select" required>
                @foreach (\App\Models\Expense::CATEGORIES as $value => $label)
                    <option value="{{ $value }}" @selected(old('category', $expense->category) === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="col-md-4">
            <div class="form-label">نوع هزینه</div>
            @foreach (\App\Models\Expense::COST_TYPES as $value => $label)
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="radio" name="cost_type" id="cost-{{ $value }}" value="{{ $value }}" @checked(old('cost_type', $expense->cost_type) === $value)>
                    <label class="form-check-label" for="cost-{{ $value }}">{{ $label }}</label>
                </div>
            @endforeach
        </div>

        <div class="col-md-4">
            <label for="incurred_on" class="form-label">تاریخ</label>
            <input id="incurred_on" type="date" name="incurred_on" required value="{{ old('incurred_on', $expense->incurred_on?->format('Y-m-d')) }}"
                @class(['form-control', 'is-invalid' => $errors->has('incurred_on')])>
            @error('incurred_on')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-4">
            <label for="amount_toman" class="form-label">مبلغ (تومان)</label>
            <input id="amount_toman" type="text" inputmode="numeric" name="amount_toman" required
                value="{{ old('amount_toman', $expense->amount_rial ? number_format(\App\Support\Money::rialToToman($expense->amount_rial)) : '') }}"
                @class(['form-control', 'ltr', 'is-invalid' => $errors->has('amount_toman')])>
            @error('amount_toman')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-8">
            <label for="description" class="form-label">شرح</label>
            <input id="description" type="text" name="description" maxlength="255" required value="{{ old('description', $expense->description) }}"
                placeholder="مثلاً آگهی دیوار هفته اول" @class(['form-control', 'is-invalid' => $errors->has('description')])>
            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

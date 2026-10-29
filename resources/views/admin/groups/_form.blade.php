@csrf

<div class="card-body">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">نام گروه <span class="text-danger">*</span></label>
            <input id="name" type="text" name="name" maxlength="255" required value="{{ old('name', $group->name) }}"
                @class(['form-control', 'is-invalid' => $errors->has('name')])>
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <div class="form-label">نوع</div>
            @if ($group->exists)
                <input type="hidden" name="type" value="{{ $group->type }}">
                <div>{{ $group->isDynamic() ? 'پویا (بر اساس قواعد)' : 'دستی' }}</div>
            @else
                @foreach (['dynamic' => 'پویا (بر اساس قواعد)', 'manual' => 'دستی'] as $type => $label)
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="type" id="type-{{ $type }}" value="{{ $type }}"
                            @checked(old('type', $group->type) === $type)>
                        <label class="form-check-label" for="type-{{ $type }}">{{ $label }}</label>
                    </div>
                @endforeach
            @endif
            @error('type')<div class="text-danger small">{{ $message }}</div>@enderror
        </div>

        <div class="col-12">
            <label for="description" class="form-label">توضیح</label>
            <textarea id="description" name="description" rows="2" maxlength="2000" class="form-control">{{ old('description', $group->description) }}</textarea>
        </div>
    </div>

    <fieldset id="rules-section" class="mt-4" @if (old('type', $group->type) !== 'dynamic') hidden @endif>
        <legend class="h6">قواعد عضویت (همه با هم)</legend>
        @error('rules')<div class="alert alert-danger small py-2">{{ $message }}</div>@enderror
        @include('admin.contacts._rules', ['prefix' => 'rules', 'values' => $group->rules ?? [], 'withSearch' => false])
    </fieldset>
</div>

@push('scripts')
<script>
document.querySelectorAll('input[name="type"][type="radio"]').forEach(function (radio) {
    radio.addEventListener('change', function () {
        document.getElementById('rules-section').hidden = this.value !== 'dynamic';
    });
});
</script>
@endpush

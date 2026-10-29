@csrf

@php($isSelf = $user->exists && auth()->user()->is($user))

<div class="card-body">
    <div class="row g-3">
        <div class="col-md-6">
            <label for="name" class="form-label">نام <span class="text-danger">*</span></label>
            <input
                id="name" type="text" name="name" maxlength="255" required
                value="{{ old('name', $user->name) }}"
                @class(['form-control', 'is-invalid' => $errors->has('name')])
            >
            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label for="email" class="form-label">ایمیل ورود <span class="text-danger">*</span></label>
            <input
                id="email" type="email" name="email" maxlength="255" required autocomplete="off"
                value="{{ old('email', $user->email) }}"
                @class(['form-control', 'ltr', 'is-invalid' => $errors->has('email')])
            >
            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label for="role" class="form-label">نقش</label>
            <select
                id="role" name="role"
                @disabled($isSelf)
                @class(['form-select', 'is-invalid' => $errors->has('role')])
            >
                @if ($user->exists)
                    <option value="">بدون دسترسی (غیرفعال)</option>
                @endif
                @foreach (\App\Enums\AdminRole::cases() as $role)
                    <option value="{{ $role->value }}" @selected(old('role', $user->role?->value) === $role->value)>
                        {{ $role->label() }}
                    </option>
                @endforeach
            </select>
            @if ($isSelf)
                {{-- فیلد غیرفعال ارسال نمی‌شود؛ مقدار فعلی جداگانه فرستاده می‌شود --}}
                <input type="hidden" name="role" value="{{ $user->role?->value }}">
                <div class="form-text">نقش حساب خودتان قابل تغییر نیست.</div>
            @endif
            @error('role')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <hr>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="password" class="form-label">
                رمز عبور
                @unless ($user->exists)<span class="text-danger">*</span>@endunless
            </label>
            <input
                id="password" type="password" name="password" autocomplete="new-password"
                @required(! $user->exists)
                @class(['form-control', 'ltr', 'is-invalid' => $errors->has('password')])
            >
            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            <div class="form-text">
                حداقل ۱۰ کاراکتر.
                @if ($user->exists) خالی بماند تا تغییر نکند؛ با تغییر رمز، کاربر از همه دستگاه‌ها خارج می‌شود. @endif
            </div>
        </div>

        <div class="col-md-6">
            <label for="password_confirmation" class="form-label">تکرار رمز عبور</label>
            <input
                id="password_confirmation" type="password" name="password_confirmation" autocomplete="new-password"
                class="form-control ltr"
            >
        </div>
    </div>
</div>

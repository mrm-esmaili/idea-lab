@extends('layouts.admin')

@section('title', 'ارسال گروهی جدید')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.sms.broadcasts.index') }}">ارسال گروهی</a></li>
    <li class="breadcrumb-item active" aria-current="page">جدید</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.sms.broadcasts.store') }}">
        @csrf

        <div class="alert alert-info small">
            پیامک گروهی <strong>تبلیغاتی</strong> است: فقط به مخاطبانی ارسال می‌شود که رضایت پیامک تبلیغاتی داده‌اند،
            متن «{{ $footer }}» به انتهای آن افزوده می‌شود و فقط در ساعات مجاز ارسال می‌شود.
            پس از ذخیره، تعداد گیرندگان را می‌بینید و ارسال فقط با تأیید نهایی انجام می‌شود.
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">پیام</h2></div>
            <div class="card-body">
                <div class="mb-3" style="max-width: 480px">
                    <label for="title" class="form-label">عنوان داخلی</label>
                    <input id="title" name="title" value="{{ old('title', $broadcast->title) }}" maxlength="150" required @class(['form-control', 'is-invalid' => $errors->has('title')])>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-1" style="max-width: 640px">
                    <label for="body" class="form-label">متن پیام</label>
                    <textarea id="body" name="body" rows="4" maxlength="{{ $maxBody }}" required @class(['form-control', 'is-invalid' => $errors->has('body')])>{{ old('body', $broadcast->body) }}</textarea>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-text mb-3">
                    متن برای همه گیرندگان یکسان است (بدون متغیر). <span id="body-counter"></span>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">گیرندگان</h2></div>
            <div class="card-body">
                @php($audience = old('audience', $broadcast->audience))
                <div class="mb-3">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="audience" id="audience-rules" value="rules" @checked($audience === 'rules')>
                        <label class="form-check-label" for="audience-rules">بر اساس قواعد (ایده، کمپین، ...)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="audience" id="audience-group" value="group" @checked($audience === 'group')>
                        <label class="form-check-label" for="audience-group">یک گروه</label>
                    </div>
                </div>

                <div class="mb-3" style="max-width: 360px">
                    <label for="contact_group_id" class="form-label">گروه</label>
                    <select id="contact_group_id" name="contact_group_id" @class(['form-select', 'is-invalid' => $errors->has('contact_group_id')])>
                        <option value="">—</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" @selected((int) old('contact_group_id', $broadcast->contact_group_id) === $group->id)>
                                {{ $group->name }} ({{ $group->type === 'dynamic' ? 'پویا' : 'دستی' }})
                            </option>
                        @endforeach
                    </select>
                    @error('contact_group_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <div class="form-label">قواعد (برای حالت «بر اساس قواعد»)</div>
                    @include('admin.contacts._rules', ['prefix' => 'rules', 'values' => $broadcast->rules ?? [], 'withSearch' => false])
                </div>

                <div style="max-width: 360px">
                    <label for="frequency_cap_hours" class="form-label">حداقل فاصله از آخرین پیامک تبلیغاتی هر مخاطب (ساعت)</label>
                    <input id="frequency_cap_hours" type="number" name="frequency_cap_hours" min="0" max="720" value="{{ old('frequency_cap_hours', $broadcast->frequency_cap_hours) }}" @class(['form-control', 'is-invalid' => $errors->has('frequency_cap_hours')])>
                    @error('frequency_cap_hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ذخیره و پیش‌نمایش</button>
                <a href="{{ route('admin.sms.broadcasts.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </div>
    </form>

    <script>
        (() => {
            const body = document.getElementById('body');
            const counter = document.getElementById('body-counter');
            const footer = @json("\n" . $footer);
            const update = () => {
                const text = body.value.trim() + footer;
                const unicode = /[^\x00-\x7F]/.test(text);
                const [single, multi] = unicode ? [70, 67] : [160, 153];
                const parts = text.length <= single ? 1 : Math.ceil(text.length / multi);
                counter.textContent = `${text.length} نویسه با متن لغو — ${parts} بخش پیامک برای هر گیرنده.`;
            };
            body.addEventListener('input', update);
            update();
        })();
    </script>
@endsection

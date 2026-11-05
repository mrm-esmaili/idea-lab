@extends('layouts.admin')

@section('title', 'قالب پیامک: ' . $template->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.sms.index') }}">پیامک</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $template->name }}</li>
@endsection

@section('content')
    <div class="card" style="max-width: 720px">
        <form method="POST" action="{{ route('admin.sms.templates.update', $template) }}">
            @csrf
            @method('PUT')

            <div class="card-body">
                @if ($template->description)
                    <p class="text-body-secondary">{{ $template->description }}</p>
                @endif

                <div class="alert alert-info small">
                    قالب را در پنل SMS.ir (بخش ارسال سریع / قالب‌ها) بسازید و پس از تأیید، شناسه آن را اینجا وارد کنید.
                    نام هر پارامتر باید دقیقاً با نام متغیر داخل متن قالب در SMS.ir یکی باشد (مثلاً <span class="ltr">#CODE#</span> ← <span class="ltr">CODE</span>).
                </div>

                <div class="mb-3" style="max-width: 280px">
                    <label for="provider_template_id" class="form-label">شناسه قالب SMS.ir</label>
                    <input
                        id="provider_template_id" type="number" min="1" name="provider_template_id"
                        value="{{ old('provider_template_id', $template->provider_template_id) }}"
                        @class(['form-control', 'ltr', 'is-invalid' => $errors->has('provider_template_id')])
                    >
                    @error('provider_template_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                @if ($template->category !== 'otp')
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="enabled" name="enabled" value="1" @checked(old('enabled', $template->enabled))>
                        <label class="form-check-label" for="enabled">ارسال این پیامک فعال باشد</label>
                        <div class="form-text">تا فعال نشود، رویداد مربوط پیامکی ایجاد نمی‌کند.</div>
                    </div>
                @endif

                <div class="mb-3">
                    <label for="body" class="form-label">متن قالب (همان متن ثبت‌شده در SMS.ir)</label>
                    <textarea id="body" name="body" rows="3" maxlength="1000" @class(['form-control', 'is-invalid' => $errors->has('body')])>{{ old('body', $template->body) }}</textarea>
                    <div class="form-text" id="body-counter" aria-live="polite"></div>
                    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">فقط برای مرجع و پیش‌نمایش؛ متن ارسالی همان است که در پنل SMS.ir تأیید شده.</div>
                </div>

                @if ($template->body)
                    @php
                        $preview = $template->render($samples);
                        $longest = $template->render(collect($stats)->map(fn ($stat) => \App\Support\SmsText::fit($stat['sample']))->all() + $samples);
                    @endphp
                    <div class="mb-3">
                        <div class="form-label">پیش‌نمایش با مقادیر نمونه</div>
                        <div class="border rounded p-2 bg-body-tertiary small" style="white-space: pre-line">{{ $preview }}</div>
                        <div class="form-text">
                            {{ number_format(mb_strlen($preview)) }} نویسه ({{ \App\Support\SmsText::parts($preview) }} پیامک).
                            با بلندترین مقادیر فعلی: {{ number_format(mb_strlen($longest)) }} نویسه ({{ \App\Support\SmsText::parts($longest) }} پیامک).
                        </div>
                    </div>
                @endif

                {{-- طول مقدار هر پارامتر در داده فعلی؛ SMS.ir بیش از ۲۵ نویسه را رد می‌کند --}}
                <div class="mb-3">
                    <div class="form-label">طول مقدار پارامترها (حد SMS.ir: {{ $paramLimit }} نویسه)</div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle small mb-1">
                            <thead><tr><th>متغیر</th><th>بلندترین مقدار فعلی</th><th>طول</th><th></th></tr></thead>
                            <tbody>
                                @foreach (array_keys($template->parameters) as $variable)
                                    @php($stat = $stats[$variable] ?? ['max' => 0, 'sample' => '', 'over' => []])
                                    <tr>
                                        <td>{{ config("statuses.sms_variable.{$variable}", $variable) }}</td>
                                        <td><bdi>{{ \Illuminate\Support\Str::limit($stat['sample'] ?: '—', 60) }}</bdi></td>
                                        <td class="text-nowrap">{{ $stat['max'] }}</td>
                                        <td>
                                            @if ($paramLimit > 0 && $stat['max'] > $paramLimit)
                                                <span class="badge text-bg-warning">کوتاه می‌شود</span>
                                            @else
                                                <span class="badge text-bg-success">مجاز</span>
                                            @endif
                                        </td>
                                    </tr>
                                    @if ($paramLimit > 0 && count($stat['over']) > 1)
                                        <tr><td></td><td colspan="3" class="text-body-secondary">موارد دیگر بلندتر از حد: @foreach (array_slice($stat['over'], 1) as $value)<bdi>{{ $value }}</bdi> ({{ mb_strlen($value) }})@if (! $loop->last)، @endif @endforeach</td></tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="form-text">
                        مقدار بلندتر از {{ $paramLimit }} نویسه هنگام ارسال خودکار کوتاه و با «…» تمام می‌شود تا پیامک رد نشود؛
                        مثلاً «{{ \App\Support\SmsText::fit('مجموعه نرم‌افزاری مدیریت باشگاه‌های ورزشی') }}».
                        برای نام کامل، نام ایده را کوتاه‌تر کنید. لینک‌ها به شکل کوتاه (<span class="ltr">{{ \App\Support\SmsText::shortUrl(route('short.account')) }}</span>) ارسال می‌شوند.
                    </div>
                </div>

                @foreach ($template->parameters as $variable => $name)
                    <div class="mb-3" style="max-width: 280px">
                        <label for="param-{{ $variable }}" class="form-label">
                            نام پارامتر برای «{{ config("statuses.sms_variable.{$variable}", $variable) }}»
                        </label>
                        <input
                            id="param-{{ $variable }}" type="text" name="parameters[{{ $variable }}]" maxlength="50" @required(! in_array($variable, \App\Models\SmsTemplate::OPTIONAL_VARIABLES, true))
                            value="{{ old("parameters.{$variable}", $name) }}"
                            @class(['form-control', 'ltr', 'is-invalid' => $errors->has("parameters.{$variable}")])
                        >
                        @error("parameters.{$variable}")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        @if (in_array($variable, \App\Models\SmsTemplate::OPTIONAL_VARIABLES, true))
                            <div class="form-text">اختیاری؛ فقط اگر قالب SMS.ir این متغیر را دارد (مثلاً <span class="ltr">#LINK#</span> ← <span class="ltr">LINK</span>). خالی یعنی ارسال نمی‌شود.</div>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ذخیره</button>
                <a href="{{ route('admin.sms.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        // شمارش زنده نویسه‌های متن قالب (فارسی: ۷۰ نویسه یک پیامک، چندبخشی ۶۷ نویسه هر بخش)
        document.addEventListener('DOMContentLoaded', function () {
            const body = document.getElementById('body');
            const counter = document.getElementById('body-counter');

            function update() {
                const text = body.value;
                const length = Array.from(text).length;
                const unicode = /[^\x00-\x7F]/.test(text);
                const [single, multi] = unicode ? [70, 67] : [160, 153];
                const parts = length === 0 ? 0 : (length <= single ? 1 : Math.ceil(length / multi));
                counter.textContent = length.toLocaleString('fa-IR') + ' نویسه — ' + parts.toLocaleString('fa-IR') + ' پیامک (متغیرهای #...# پس از جایگزینی طول متفاوتی دارند؛ پیش‌نمایش پایین را ببینید)';
            }

            body.addEventListener('input', update);
            update();
        });
    </script>
@endpush

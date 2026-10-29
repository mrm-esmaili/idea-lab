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

                @foreach ($template->parameters as $variable => $name)
                    <div class="mb-3" style="max-width: 280px">
                        <label for="param-{{ $variable }}" class="form-label">
                            نام پارامتر برای «{{ config("statuses.sms_variable.{$variable}", $variable) }}»
                        </label>
                        <input
                            id="param-{{ $variable }}" type="text" name="parameters[{{ $variable }}]" maxlength="50" required
                            value="{{ old("parameters.{$variable}", $name) }}"
                            @class(['form-control', 'ltr', 'is-invalid' => $errors->has("parameters.{$variable}")])
                        >
                        @error("parameters.{$variable}")<div class="invalid-feedback">{{ $message }}</div>@enderror
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

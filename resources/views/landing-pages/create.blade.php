@extends('layouts.admin')

@section('title', 'ساخت صفحه فرود')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ideas.show', $idea) }}">{{ $idea->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">صفحه فرود جدید</li>
@endsection

@section('content')
    <div class="card" style="max-width: 760px">
        <form action="{{ route('landing-pages.store', $idea) }}" method="POST">
            @csrf

            <div class="card-body">
                <p class="text-body-secondary">
                    ایده: <strong>{{ $idea->name }}</strong>
                </p>

                @foreach ([
                    ['name' => 'name', 'label' => 'نام صفحه فرود', 'placeholder' => 'مثلاً صفحه معرفی باشگاه‌یار', 'required' => true],
                    ['name' => 'title', 'label' => 'عنوان اصلی', 'placeholder' => 'مثلاً باشگاهت را هوشمند مدیریت کن', 'required' => true],
                    ['name' => 'cta_text', 'label' => 'متن دکمه', 'placeholder' => 'ثبت‌نام در لیست انتظار', 'required' => false],
                ] as $field)
                    <div class="mb-3">
                        <label for="{{ $field['name'] }}" class="form-label">
                            {{ $field['label'] }}
                            @if ($field['required'])
                                <span class="text-danger">*</span>
                            @endif
                        </label>
                        <input
                            id="{{ $field['name'] }}"
                            type="text"
                            name="{{ $field['name'] }}"
                            value="{{ old($field['name']) }}"
                            placeholder="{{ $field['placeholder'] }}"
                            maxlength="255"
                            @required($field['required'])
                            @class(['form-control', 'is-invalid' => $errors->has($field['name'])])
                        >
                        @error($field['name'])
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @endforeach

                <div class="mb-0">
                    <label for="description" class="form-label">توضیحات</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        @class(['form-control', 'is-invalid' => $errors->has('description')])
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ساخت صفحه فرود</button>
                <a href="{{ route('ideas.show', $idea) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

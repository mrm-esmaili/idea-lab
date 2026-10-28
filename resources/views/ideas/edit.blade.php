@extends('layouts.admin')

@section('title', 'ویرایش ایده')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ideas.show', $idea) }}">{{ $idea->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">ویرایش</li>
@endsection

@section('content')
    <div class="card" style="max-width: 760px">
        <form action="{{ route('ideas.update', $idea) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card-body">
                <div class="mb-3">
                    <label for="name" class="form-label">نام ایده <span class="text-danger">*</span></label>
                    <input
                        id="name" type="text" name="name" maxlength="255" required
                        value="{{ old('name', $idea->name) }}"
                        @class(['form-control', 'is-invalid' => $errors->has('name')])
                    >
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-0">
                    <label for="description" class="form-label">توضیحات</label>
                    <textarea
                        id="description" name="description" rows="5" maxlength="5000"
                        @class(['form-control', 'is-invalid' => $errors->has('description')])
                    >{{ old('description', $idea->description) }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <p class="form-text mb-0 mt-3">وضعیت ایده از صفحه جزئیات و با ثبت دلیل تغییر می‌کند.</p>
            </div>

            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                <a href="{{ route('ideas.show', $idea) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'ایجاد ایده جدید')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">ایده جدید</li>
@endsection

@section('content')
    <div class="card" style="max-width: 760px">
        <form action="{{ route('ideas.store') }}" method="POST">
            @csrf

            <div class="card-body">
                <div class="mb-3">
                    <label for="name" class="form-label">نام ایده <span class="text-danger">*</span></label>
                    <input
                        id="name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        maxlength="255"
                        required
                        @class(['form-control', 'is-invalid' => $errors->has('name')])
                    >
                    @error('name')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-0">
                    <label for="description" class="form-label">توضیحات</label>
                    <textarea
                        id="description"
                        name="description"
                        rows="4"
                        maxlength="5000"
                        @class(['form-control', 'is-invalid' => $errors->has('description')])
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>

            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ایجاد ایده</button>
                <a href="{{ route('ideas.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

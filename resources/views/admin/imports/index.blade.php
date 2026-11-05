@extends('layouts.admin')

@section('title', 'ورود ایده‌ها از فایل')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">ورود از فایل</li>
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">بارگذاری فایل</h2></div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.imports.upload') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="file" class="form-label">فایل <span class="ltr">.md</span> یا <span class="ltr">.json</span> (حداکثر {{ number_format($maxKb / 1024) }} مگابایت)</label>
                            <input id="file" type="file" name="file" accept=".md,.markdown,.json,.txt,text/markdown,application/json,text/plain" @class(['form-control', 'is-invalid' => $errors->has('file')])>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label for="text" class="form-label">یا متن خروجی هوش مصنوعی را اینجا بچسبانید</label>
                            <textarea id="text" name="text" rows="6" class="form-control ltr font-monospace small" placeholder="```json&#10;{ &quot;name&quot;: ... }&#10;```">{{ old('text') }}</textarea>
                            @error('text')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> بررسی و پیش‌نمایش</button>
                        <div class="form-text">هنوز چیزی ساخته نمی‌شود؛ ابتدا فهرست ایده‌ها، صفحه‌ها، کمپین‌ها و خطاها را می‌بینید.</div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card mb-3">
                <div class="card-header"><h2 class="card-title h6 mb-0">روش کار</h2></div>
                <div class="card-body small">
                    <ol class="mb-3">
                        <li><strong>راهنمای پرامپت</strong> را دریافت کنید و همراه فایل اطلاعات خود (PDF، Word یا متن) به هوش مصنوعی بدهید.</li>
                        <li>پرامپت داخل راهنما را بفرستید. اگر لازم است تعداد ایده‌ها و کمپین‌ها را در آن تغییر دهید.</li>
                        <li>خروجی را با پسوند <span class="ltr">.md</span> ذخیره و اینجا بارگذاری کنید، یا متن را بچسبانید.</li>
                        <li>در پیش‌نمایش خطاها را ببینید و تأیید کنید.</li>
                        <li>ایده‌ها «پیش‌نویس» و صفحه‌ها <strong>منتشرنشده</strong> ساخته می‌شوند. بازبینی کنید، تصویر و ویدیو بگذارید و منتشر کنید.</li>
                    </ol>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('admin.imports.guide') }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-robot"></i> دریافت راهنمای پرامپت</a>
                        <a href="{{ route('admin.imports.sample') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-text"></i> دریافت فایل نمونه</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

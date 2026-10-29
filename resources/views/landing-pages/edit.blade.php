@extends('layouts.admin')

@section('title', 'ویرایش صفحه فرود')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ideas.show', $idea) }}">{{ $idea->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $landingPage->name }}</li>
@endsection

@section('page-actions')
    <a
        href="{{ route('landing-pages.show', $landingPage->slug) }}"
        target="_blank"
        rel="noopener noreferrer"
        class="btn btn-outline-primary"
    >
        <i class="bi bi-box-arrow-up-left"></i>
        مشاهده صفحه
    </a>
@endsection

@section('content')
    @unless ($landingPage->is_active)
        <div class="alert alert-warning">این صفحه غیرفعال است و برای عموم نمایش داده نمی‌شود.</div>
    @endunless

    <form
        action="{{ route('landing-pages.update', $landingPage) }}"
        method="POST"
        enctype="multipart/form-data"
    >
        @method('PUT')
        @include('landing-pages._form')

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
            <a href="{{ route('ideas.show', $idea) }}" class="btn btn-outline-secondary">انصراف</a>
        </div>
    </form>
@endsection

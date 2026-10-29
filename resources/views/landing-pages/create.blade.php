@extends('layouts.admin')

@section('title', 'ساخت صفحه فرود')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('ideas.show', $idea) }}">{{ $idea->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">صفحه فرود جدید</li>
@endsection

@section('content')
    <form
        action="{{ route('landing-pages.store', $idea) }}"
        method="POST"
        enctype="multipart/form-data"
    >
        @include('landing-pages._form')

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">ساخت و انتشار صفحه فرود</button>
            <a href="{{ route('ideas.show', $idea) }}" class="btn btn-outline-secondary">انصراف</a>
        </div>
    </form>
@endsection

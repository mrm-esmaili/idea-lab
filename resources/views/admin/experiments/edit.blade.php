@extends('layouts.admin')

@section('title', 'ویرایش آزمایش')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.experiments.index') }}">آزمایش‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.experiments.show', $experiment) }}">{{ $experiment->title }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">ویرایش</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.experiments.update', $experiment) }}">
        @csrf
        @method('PUT')
        <div class="alert alert-light border small">فقط تا پیش از شروع آزمایش می‌توان فرضیه، بازه و معیارها را تغییر داد.</div>
        @include('admin.experiments._form')
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">ذخیره</button>
            <a href="{{ route('admin.experiments.show', $experiment) }}" class="btn btn-outline-secondary">انصراف</a>
        </div>
    </form>
@endsection

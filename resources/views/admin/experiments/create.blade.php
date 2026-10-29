@extends('layouts.admin')

@section('title', 'آزمایش جدید')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.experiments.index') }}">آزمایش‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">جدید</li>
@endsection

@section('content')
    <form method="POST" action="{{ route('admin.experiments.store') }}">
        @csrf
        @include('admin.experiments._form')
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">ثبت آزمایش</button>
            <a href="{{ route('admin.experiments.index') }}" class="btn btn-outline-secondary">انصراف</a>
        </div>
    </form>
@endsection

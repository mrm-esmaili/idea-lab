@extends('layouts.admin')

@section('title', 'کمپین جدید')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.index') }}">کمپین‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">کمپین جدید</li>
@endsection

@section('content')
    <div class="card" style="max-width: 960px">
        <form method="POST" action="{{ route('admin.campaigns.store') }}">
            @include('admin.campaigns._form')
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ایجاد کمپین</button>
                <a href="{{ route('admin.campaigns.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'گروه جدید')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.groups.index') }}">گروه‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">گروه جدید</li>
@endsection

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.groups.store') }}">
            @include('admin.groups._form')
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ساخت گروه</button>
                <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

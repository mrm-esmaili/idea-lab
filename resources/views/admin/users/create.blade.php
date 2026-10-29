@extends('layouts.admin')

@section('title', 'کاربر جدید')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.users.index') }}">کاربران</a></li>
    <li class="breadcrumb-item active" aria-current="page">کاربر جدید</li>
@endsection

@section('content')
    <div class="card" style="max-width: 860px">
        <form method="POST" action="{{ route('admin.users.store') }}">
            @include('admin.users._form')

            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ایجاد کاربر</button>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'ویرایش گروه')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.groups.index') }}">گروه‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.groups.show', $group) }}">{{ $group->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">ویرایش</li>
@endsection

@section('content')
    <div class="card">
        <form method="POST" action="{{ route('admin.groups.update', $group) }}">
            @method('PUT')
            @include('admin.groups._form')
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ذخیره</button>
                <a href="{{ route('admin.groups.show', $group) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

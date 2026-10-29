@extends('layouts.admin')

@section('title', 'ویرایش کمپین')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.index') }}">کمپین‌ها</a></li>
    <li class="breadcrumb-item"><a href="{{ route('admin.campaigns.show', $campaign) }}">{{ $campaign->name }}</a></li>
    <li class="breadcrumb-item active" aria-current="page">ویرایش</li>
@endsection

@section('content')
    <div class="card" style="max-width: 960px">
        <form method="POST" action="{{ route('admin.campaigns.update', $campaign) }}">
            @method('PUT')
            @include('admin.campaigns._form')
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ذخیره تغییرات</button>
                <a href="{{ route('admin.campaigns.show', $campaign) }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

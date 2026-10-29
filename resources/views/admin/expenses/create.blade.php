@extends('layouts.admin')

@section('title', 'ثبت هزینه')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('admin.expenses.index') }}">هزینه‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">ثبت هزینه</li>
@endsection

@section('content')
    <div class="card" style="max-width: 900px">
        <form method="POST" action="{{ route('admin.expenses.store') }}">

            @include('admin.expenses._form')
            <div class="card-footer d-flex gap-2">
                <button type="submit" class="btn btn-primary">ثبت</button>
                <a href="{{ route('admin.expenses.index') }}" class="btn btn-outline-secondary">انصراف</a>
            </div>
        </form>
    </div>
@endsection

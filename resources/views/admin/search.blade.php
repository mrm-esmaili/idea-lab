@extends('layouts.admin')

@section('title', 'جست‌وجو')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">جست‌وجو</li>
@endsection

@section('content')
    <form method="GET" action="{{ route('admin.search') }}" class="mb-3" role="search">
        <div class="input-group" style="max-width: 640px">
            <input type="search" name="q" value="{{ $q }}" maxlength="100" class="form-control" aria-label="عبارت جست‌وجو" placeholder="نام، موبایل، #شناسه سفارش، کد پیگیری، نام ایده یا کمپین" autofocus>
            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> جست‌وجو</button>
        </div>
    </form>

    @if (mb_strlen($q) < 2)
        <p class="text-body-secondary">دست‌کم دو نویسه وارد کنید.</p>
    @elseif ($sections === [])
        <div class="alert alert-light border">نتیجه‌ای برای «{{ $q }}» پیدا نشد.</div>
    @else
        <div class="row g-3">
            @foreach ($sections as [$title, $items])
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header"><h2 class="card-title h6 mb-0">{{ $title }} <span class="badge text-bg-light">{{ $items->count() }}</span></h2></div>
                        <div class="list-group list-group-flush">
                            @foreach ($items as [$label, $detail, $url])
                                <a href="{{ $url }}" class="list-group-item list-group-item-action">
                                    <div>{{ $label }}</div>
                                    <div class="small text-body-secondary">{{ $detail }}</div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <p class="small text-body-secondary mt-3">از هر بخش حداکثر ۸ نتیجه نمایش داده می‌شود؛ فقط بخش‌هایی که مجوزشان را دارید جست‌وجو می‌شوند.</p>
    @endif
@endsection

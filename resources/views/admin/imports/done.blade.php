@extends('layouts.admin')

@section('title', 'نتیجه ورود از فایل')

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">نتیجه ورود</li>
@endsection

@section('content')
    <div class="card">
        <div class="card-header"><h2 class="card-title h6 mb-0">کارهای بعدی برای هر ایده</h2></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead><tr><th>ایده</th><th>صفحه فرود</th><th>کمپین</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($ideas as $idea)
                            @foreach ($idea->landingPages->whenEmpty(fn ($c) => collect([null])) as $page)
                                <tr>
                                    <td>@if ($loop->first)<a href="{{ route('ideas.show', $idea) }}">{{ $idea->name }}</a>@endif</td>
                                    <td>{{ $page?->title ?? '—' }}</td>
                                    <td>{{ $page ? number_format($page->campaigns->count()) : '—' }}</td>
                                    <td class="text-end text-nowrap">
                                        @if ($page)
                                            <a href="{{ route('landing-pages.edit', $page) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> ویرایش و تصویر</a>
                                            <a href="{{ route('landing-pages.preview', $page) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> پیش‌نمایش</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        @empty
                            <x-admin.empty-row colspan="4">موردی نیست.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer small text-body-secondary">
            پس از بازبینی متن‌ها و افزودن تصویر یا ویدیو، صفحه را از صفحه ایده منتشر کنید. برای فعال شدن پیش‌خرید، شرایط بازپرداخت و مبلغ پیش‌پرداخت لازم است.
        </div>
    </div>
@endsection

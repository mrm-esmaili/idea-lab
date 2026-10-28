@extends('layouts.admin')

@section('title', $idea->name)

@section('breadcrumbs')
    <li class="breadcrumb-item"><a href="{{ route('ideas.index') }}">ایده‌ها</a></li>
    <li class="breadcrumb-item active" aria-current="page">{{ $idea->name }}</li>
@endsection

@section('page-actions')
    <a href="{{ route('landing-pages.create', $idea) }}" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i>
        ساخت صفحه فرود
    </a>
@endsection

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <div class="mb-2">
                وضعیت:
                <x-admin.status-badge group="idea" :value="$idea->status" />
            </div>

            <p class="mb-0 text-body-secondary">
                {{ $idea->description ?: 'توضیحی ثبت نشده است.' }}
            </p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h2 class="card-title h5 mb-0">صفحات فرود</h2>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>نام</th>
                            <th>عنوان</th>
                            <th>آدرس</th>
                            <th>وضعیت</th>
                            <th>لینک</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($idea->landingPages as $landingPage)
                            <tr>
                                <td class="fw-semibold">{{ $landingPage->name }}</td>
                                <td>{{ $landingPage->title }}</td>
                                <td class="ltr small">/l/{{ $landingPage->slug }}</td>
                                <td>
                                    @if ($landingPage->is_active)
                                        <span class="badge text-bg-success">فعال</span>
                                    @else
                                        <span class="badge text-bg-secondary">غیرفعال</span>
                                    @endif
                                </td>
                                <td>
                                    <a
                                        href="{{ route('landing-pages.show', $landingPage->slug) }}"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        <i class="bi bi-box-arrow-up-left"></i>
                                        مشاهده
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="5" icon="bi-window">
                                هنوز صفحه فرودی برای این ایده ساخته نشده است.
                            </x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@extends('layouts.admin')

@section('title', 'درخواست‌های بازپرداخت')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">بازپرداخت‌ها</li>
@endsection

@section('content')
    <div class="d-flex flex-wrap gap-2 mb-3">
        @foreach (config('statuses.refund_request') as $value => $label)
            <a href="{{ route('admin.refunds.index', ['status' => $value]) }}"
               @class(['btn', 'btn-sm', 'btn-primary' => request('status') === $value, 'btn-outline-secondary' => request('status') !== $value])>
                {{ $label }}
                <span class="badge text-bg-light ms-1">{{ number_format($counts[$value] ?? 0) }}</span>
            </a>
        @endforeach
        <a href="{{ route('admin.refunds.index') }}" class="btn btn-sm btn-link">همه</a>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>مشتری</th>
                            <th>ایده</th>
                            <th>مبلغ درخواست</th>
                            <th>ثبت‌کننده</th>
                            <th>وضعیت</th>
                            <th>زمان</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($requests as $refundRequest)
                            <tr>
                                <td><a href="{{ route('admin.refunds.show', $refundRequest) }}" class="ltr">#{{ $refundRequest->id }}</a></td>
                                <td>
                                    <a href="{{ route('admin.refunds.show', $refundRequest) }}">{{ $refundRequest->preorder->name }}</a>
                                    <div class="small text-body-secondary ltr text-end">{{ $refundRequest->preorder->phone }}</div>
                                </td>
                                <td>{{ $refundRequest->preorder->landingPage?->idea?->name }}</td>
                                <td class="text-nowrap">{{ \App\Support\Money::formatRialAsToman($refundRequest->requested_amount_rial) }}</td>
                                <td>{{ $refundRequest->requested_by_type === 'admin' ? 'مدیر' : 'مشتری' }}</td>
                                <td><x-admin.status-badge group="refund_request" :value="$refundRequest->status" /></td>
                                <td class="ltr small text-nowrap">{{ \App\Support\Format::datetime($refundRequest->created_at) }}</td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="7" icon="bi-arrow-counterclockwise">درخواستی پیدا نشد.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if ($requests->hasPages())
            <div class="card-footer">{{ $requests->links() }}</div>
        @endif
    </div>
@endsection

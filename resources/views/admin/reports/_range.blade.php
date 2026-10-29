{{-- فیلتر بازه مشترک گزارش‌ها؛ با $ideas فیلتر ایده هم نمایش داده می‌شود --}}
<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            @isset($ideas)
                <div class="col-sm-6 col-lg-3">
                    <label for="r-idea" class="form-label">ایده</label>
                    <select id="r-idea" name="idea" class="form-select">
                        <option value="">همه</option>
                        @foreach ($ideas as $idea)
                            <option value="{{ $idea->id }}" @selected((int) ($filters['idea'] ?? 0) === $idea->id)>{{ $idea->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endisset
            <div class="col-sm-6 col-lg-3">
                <label for="r-from" class="form-label">از تاریخ</label>
                <input id="r-from" type="date" name="from" value="{{ $filters['from'] }}" class="form-control">
            </div>
            <div class="col-sm-6 col-lg-3">
                <label for="r-to" class="form-label">تا تاریخ</label>
                <input id="r-to" type="date" name="to" value="{{ $filters['to'] }}" class="form-control">
            </div>
            <div class="col-sm-6 col-lg-2">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> اعمال</button>
            </div>
        </form>
        <div class="small text-body-secondary mt-2">
            بازه: {{ \App\Support\Jalali::format(\Illuminate\Support\Carbon::parse($filters['from']), 'j F Y') }}
            تا {{ \App\Support\Jalali::format(\Illuminate\Support\Carbon::parse($filters['to']), 'j F Y') }} (به وقت تهران)
        </div>
    </div>
</div>

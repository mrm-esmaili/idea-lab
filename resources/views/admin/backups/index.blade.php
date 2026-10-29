@extends('layouts.admin')

@section('title', 'پشتیبان‌گیری')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">پشتیبان‌گیری</li>
@endsection

@section('page-actions')
    <form method="POST" action="{{ route('admin.backups.store') }}" data-confirm="یک پشتیبان کامل از پایگاه داده ساخته شود؟">
        @csrf
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-database-down"></i> ساخت پشتیبان اکنون</button>
    </form>
@endsection

@section('content')
    <div class="alert alert-warning small">
        فایل‌های پشتیبان شامل اطلاعات شخصی مخاطبان و سوابق مالی‌اند؛ آن‌ها را فقط در جای امن نگه دارید. هر دریافت در سابقه ممیزی ثبت می‌شود.
    </div>

    <div class="card mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>فایل</th><th>زمان</th><th>حجم</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($files as $file)
                            <tr>
                                <td class="ltr text-end small">{{ $file['name'] }}</td>
                                <td class="small text-nowrap">{{ \App\Support\Format::datetime($file['created_at']) }}</td>
                                <td class="small text-nowrap">{{ number_format($file['size'] / 1024, 1) }} KB</td>
                                <td class="text-end">
                                    <a href="{{ route('admin.backups.download', $file['name']) }}" class="btn btn-sm btn-outline-secondary text-nowrap"><i class="bi bi-download"></i> دریافت</a>
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty-row colspan="4" icon="bi-database">هنوز پشتیبانی ساخته نشده است.</x-admin.empty-row>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><h2 class="card-title h6 mb-0">روش پشتیبان‌گیری و بازیابی</h2></div>
        <div class="card-body small">
            <ol class="mb-2">
                <li>با Cron فعال (<span class="ltr">schedule:run</span> هر دقیقه)، هر شب ساعت ۲۳:۴۰ (UTC) یک پشتیبان خودکار ساخته و {{ $keep }} فایل آخر نگه داشته می‌شود.</li>
                <li>فایل‌ها در <span class="ltr">storage/app/backups</span> و خارج از دسترس وب‌اند؛ دست‌کم هفته‌ای یک بار یک نسخه را دریافت و بیرون از سرور نگه دارید.</li>
                <li><strong>بازیابی:</strong> cPanel ← phpMyAdmin ← پایگاه داده (ترجیحاً یک پایگاه داده خالی جدید) ← Import ← انتخاب فایل <span class="ltr">.sql.gz</span>. سپس در صورت نیاز <span class="ltr">DB_DATABASE</span> را در <span class="ltr">.env</span> به پایگاه داده جدید تغییر دهید.</li>
                <li>پشتیبان شامل ساختار و داده همه جداول است به‌جز نشست‌ها و کش (پس از بازیابی همه کاربران باید دوباره وارد شوند).</li>
                <li>تصاویر صفحات فرود (<span class="ltr">public/uploads</span>) در این فایل نیستند؛ با Backup Wizard در cPanel از کل پوشه سایت پشتیبان بگیرید.</li>
            </ol>
        </div>
    </div>
@endsection

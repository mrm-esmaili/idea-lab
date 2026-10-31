@extends('layouts.admin')

@section('title', 'تنظیمات سایت')

@section('breadcrumbs')
    <li class="breadcrumb-item active" aria-current="page">تنظیمات سایت</li>
@endsection

@section('content')
    <p class="text-body-secondary small">
        این اطلاعات در پاورقی صفحات فرود، حساب مشتری و صفحات پرداخت نمایش داده می‌شود تا مشتری بداند طرف حسابش کیست و از کجا پیگیری کند.
    </p>

    <form method="POST" action="{{ route('admin.settings.site.update') }}" enctype="multipart/form-data" style="max-width: 860px">
        @csrf
        @method('PUT')

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">شرکت ارائه‌دهنده</h2></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="company_name" class="form-label">نام شرکت</label>
                        <input id="company_name" name="company_name" type="text" maxlength="120" placeholder="ایده‌پردازان"
                            value="{{ old('company_name', $company['name']) }}" @class(['form-control', 'is-invalid' => $errors->has('company_name')])>
                        @error('company_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="company_website" class="form-label">نشانی سایت اصلی</label>
                        <input id="company_website" name="company_website" type="url" maxlength="255" placeholder="https://itmb.ir"
                            value="{{ old('company_website', $company['website']) }}" @class(['form-control', 'ltr', 'is-invalid' => $errors->has('company_website')])>
                        @error('company_website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="company_intro" class="form-label">متن کوتاه معرفی</label>
                        <textarea id="company_intro" name="company_intro" rows="2" maxlength="500"
                            placeholder="این محصول توسط شرکت ایده‌پردازان ارائه می‌شود و پرداخت و پشتیبانی آن با این شرکت است."
                            @class(['form-control', 'is-invalid' => $errors->has('company_intro')])>{{ old('company_intro', $company['intro']) }}</textarea>
                        @error('company_intro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="company_logo" class="form-label">لوگو (PNG، JPG یا WebP، حداکثر ۱ مگابایت)</label>
                        <input id="company_logo" name="company_logo" type="file" accept="image/png,image/jpeg,image/webp" @class(['form-control', 'is-invalid' => $errors->has('company_logo')])>
                        @error('company_logo')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if ($logoUrl)
                        <div class="col-md-6 d-flex align-items-end gap-3">
                            <img src="{{ $logoUrl }}" alt="لوگوی فعلی" style="max-height: 48px; max-width: 160px">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="remove_logo">
                                <label class="form-check-label" for="remove_logo">حذف لوگو</label>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">نماد اعتماد الکترونیکی (اینماد)</h2></div>
            <div class="card-body">
                <p class="small text-body-secondary">
                    در کدی که اینماد داده، نشانی شبیه <span class="ltr">trustseal.enamad.ir/?id=<b>123456</b>&amp;Code=<b>AbCd…</b></span> است؛ همین دو مقدار را وارد کنید.
                    کد HTML ذخیره نمی‌شود و نماد را خود سایت می‌سازد. اینماد باید برای همین دامنه (یا دامنه اصلی، در صورت پذیرش زیردامنه) صادر شده باشد.
                </p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="enamad_id" class="form-label">شناسه (id)</label>
                        <input id="enamad_id" name="enamad_id" type="text" inputmode="numeric" maxlength="12"
                            value="{{ old('enamad_id', $trustSeal['enamad_id']) }}" @class(['form-control', 'ltr', 'is-invalid' => $errors->has('enamad_id')])>
                        @error('enamad_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-5">
                        <label for="enamad_code" class="form-label">کد (Code)</label>
                        <input id="enamad_code" name="enamad_code" type="text" maxlength="64"
                            value="{{ old('enamad_code', $trustSeal['enamad_code']) }}" @class(['form-control', 'ltr', 'is-invalid' => $errors->has('enamad_code')])>
                        @error('enamad_code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    @if ($enamad)
                        <div class="col-md-3 small">
                            <a href="{{ $enamad['url'] }}" target="_blank" rel="noopener" referrerpolicy="origin">بررسی نماد در اینماد</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">راه‌های تماس با پشتیبانی</h2></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="support_help_text" class="form-label">متن راهنما</label>
                    <input id="support_help_text" name="support_help_text" type="text" maxlength="300"
                        placeholder="سؤال یا مشکلی دارید؟ در بله یا واتساپ پیام دهید."
                        value="{{ old('support_help_text', $support['help_text']) }}" @class(['form-control', 'is-invalid' => $errors->has('support_help_text')])>
                    @error('support_help_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <p class="small text-body-secondary mb-2">
                    برای بله، تلگرام و ایتا نام کاربری (بدون @) یا شماره موبایل؛ برای واتساپ و تلفن شماره؛ ردیف‌های بدون نوع نادیده گرفته می‌شوند.
                    واتساپ، تلگرام (نام کاربری یا شماره)، تلفن و ایمیل لینک مستقیم دارند؛ برای بله و ایتا با شماره، فقط شماره نمایش داده می‌شود.
                </p>

                @foreach ($channels as $index => $channel)
                    <div class="row g-2 mb-2 align-items-start">
                        <div class="col-sm-3">
                            <label class="visually-hidden" for="channel-type-{{ $index }}">نوع</label>
                            <select id="channel-type-{{ $index }}" name="channels[{{ $index }}][type]" class="form-select">
                                <option value="">—</option>
                                @foreach (\App\Support\ContactChannel::TYPES as $type => $label)
                                    <option value="{{ $type }}" @selected(old("channels.{$index}.type", $channel['type']) === $type)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-sm-5">
                            <label class="visually-hidden" for="channel-value-{{ $index }}">شماره یا نام کاربری</label>
                            <input id="channel-value-{{ $index }}" name="channels[{{ $index }}][value]" type="text" maxlength="120" placeholder="09121234567 یا itmb_support"
                                value="{{ old("channels.{$index}.value", $channel['value']) }}" @class(['form-control', 'ltr', 'is-invalid' => $errors->has("channels.{$index}.value")])>
                            @error("channels.{$index}.value")<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-sm-4">
                            <label class="visually-hidden" for="channel-label-{{ $index }}">عنوان (اختیاری)</label>
                            <input id="channel-label-{{ $index }}" name="channels[{{ $index }}][label]" type="text" maxlength="60" placeholder="عنوان (اختیاری)، مثلاً پشتیبانی فروش"
                                value="{{ old("channels.{$index}.label", $channel['label']) }}" class="form-control">
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-primary">ذخیره تنظیمات</button>
    </form>
@endsection

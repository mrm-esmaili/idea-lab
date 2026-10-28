@php
    $content = $landingPage->contentData();
    $faq = old('faq', $content['faq']);
    $faqRows = max(3, min(8, count($faq) + 1));
@endphp

@csrf

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">اطلاعات اصلی</h2></div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="name" class="form-label">نام داخلی صفحه <span class="text-danger">*</span></label>
                        <input
                            id="name" type="text" name="name" maxlength="255" required
                            value="{{ old('name', $landingPage->name) }}"
                            placeholder="مثلاً صفحه معرفی باشگاه‌یار"
                            @class(['form-control', 'is-invalid' => $errors->has('name')])
                        >
                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="slug" class="form-label">نامک آدرس (اختیاری)</label>
                        <div class="input-group ltr">
                            <span class="input-group-text">/l/</span>
                            <input
                                id="slug" type="text" name="slug" maxlength="80"
                                value="{{ old('slug', $landingPage->slug) }}"
                                placeholder="clubyar"
                                @class(['form-control', 'is-invalid' => $errors->has('slug')])
                            >
                            @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="form-text">
                            فقط حروف کوچک انگلیسی، عدد و خط تیره. خالی بماند تا خودکار ساخته شود.
                            @if ($landingPage->exists)
                                <span class="text-warning-emphasis">تغییر نامک، لینک‌های تبلیغاتی قبلی را از کار می‌اندازد.</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-12">
                        <label for="title" class="form-label">عنوان اصلی <span class="text-danger">*</span></label>
                        <input
                            id="title" type="text" name="title" maxlength="255" required
                            value="{{ old('title', $landingPage->title) }}"
                            placeholder="مثلاً باشگاهت را هوشمند مدیریت کن"
                            @class(['form-control', 'is-invalid' => $errors->has('title')])
                        >
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-12">
                        <label for="description" class="form-label">توضیح زیر عنوان</label>
                        <textarea
                            id="description" name="description" rows="2" maxlength="5000"
                            @class(['form-control', 'is-invalid' => $errors->has('description')])
                        >{{ old('description', $landingPage->description) }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label for="cta_text" class="form-label">متن دکمه اصلی (CTA)</label>
                        <input
                            id="cta_text" type="text" name="cta_text" maxlength="255"
                            value="{{ old('cta_text', $landingPage->cta_text) }}"
                            placeholder="ثبت‌نام در لیست انتظار"
                            @class(['form-control', 'is-invalid' => $errors->has('cta_text')])
                        >
                        @error('cta_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">خالی بماند تا دکمه نمایش داده نشود.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">محتوای صفحه</h2></div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="body_title" class="form-label">عنوان بخش معرفی</label>
                    <input
                        id="body_title" type="text" name="body_title" maxlength="255"
                        value="{{ old('body_title', $content['body_title']) }}"
                        placeholder="به‌زودی اطلاعات بیشتری منتشر می‌شود"
                        @class(['form-control', 'is-invalid' => $errors->has('body_title')])
                    >
                    @error('body_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="body_text" class="form-label">متن معرفی</label>
                    <textarea
                        id="body_text" name="body_text" rows="5" maxlength="5000"
                        @class(['form-control', 'is-invalid' => $errors->has('body_text')])
                    >{{ old('body_text', $content['body_text']) }}</textarea>
                    @error('body_text')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-0">
                    <label for="features" class="form-label">ویژگی‌ها (هر خط یک ویژگی، حداکثر ۱۲)</label>
                    <textarea
                        id="features" name="features" rows="5" maxlength="3000"
                        placeholder="مدیریت شاگردان&#10;مدیریت پرداخت&#10;گزارش‌گیری"
                        @class(['form-control', 'is-invalid' => $errors->has('features')])
                    >{{ old('features', implode("\n", $content['features'])) }}</textarea>
                    @error('features')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">پرسش‌های متداول</h2></div>
            <div class="card-body">
                <p class="form-text mt-0">ردیف‌های خالی نادیده گرفته می‌شوند. پس از ذخیره، یک ردیف خالی جدید اضافه می‌شود.</p>

                @for ($i = 0; $i < $faqRows; $i++)
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <input
                                type="text" name="faq[{{ $i }}][q]" maxlength="255"
                                value="{{ $faq[$i]['q'] ?? '' }}"
                                placeholder="پرسش {{ $i + 1 }}"
                                aria-label="پرسش {{ $i + 1 }}"
                                @class(['form-control', 'is-invalid' => $errors->has("faq.$i.q")])
                            >
                        </div>
                        <div class="col-md-7">
                            <textarea
                                name="faq[{{ $i }}][a]" rows="1" maxlength="2000"
                                placeholder="پاسخ"
                                aria-label="پاسخ پرسش {{ $i + 1 }}"
                                @class(['form-control', 'is-invalid' => $errors->has("faq.$i.a")])
                            >{{ $faq[$i]['a'] ?? '' }}</textarea>
                        </div>
                    </div>
                @endfor
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">فرم‌های صفحه</h2></div>
            <div class="card-body">
                <input type="hidden" name="show_lead_form" value="0">
                <div class="form-check form-switch mb-2">
                    <input
                        class="form-check-input" type="checkbox" role="switch"
                        id="show_lead_form" name="show_lead_form" value="1"
                        @checked(old('show_lead_form', $content['show_lead_form']))
                    >
                    <label class="form-check-label" for="show_lead_form">فرم ثبت علاقه‌مندی</label>
                </div>

                <input type="hidden" name="show_preorder_form" value="0">
                <div class="form-check form-switch">
                    <input
                        class="form-check-input" type="checkbox" role="switch"
                        id="show_preorder_form" name="show_preorder_form" value="1"
                        @checked(old('show_preorder_form', $content['show_preorder_form']))
                    >
                    <label class="form-check-label" for="show_preorder_form">فرم پیش‌خرید</label>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">تصویر اصلی</h2></div>
            <div class="card-body">
                @if ($imageUrl = $landingPage->heroImageUrl())
                    <img src="{{ $imageUrl }}" alt="تصویر فعلی" class="img-fluid rounded mb-2">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="remove_hero_image" name="remove_hero_image" value="1">
                        <label class="form-check-label" for="remove_hero_image">حذف تصویر فعلی</label>
                    </div>
                @endif

                <input
                    type="file" name="hero_image" id="hero_image"
                    accept="image/jpeg,image/png,image/webp"
                    aria-label="تصویر اصلی"
                    @class(['form-control', 'is-invalid' => $errors->has('hero_image')])
                >
                @error('hero_image')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">JPG، PNG یا WebP — حداکثر ۲ مگابایت.</div>
            </div>
        </div>
    </div>
</div>

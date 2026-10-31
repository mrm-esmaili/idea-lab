@php
    $content = $landingPage->contentData();
    $faq = old('faq', $content['faq']);
    $faqRows = max(3, min(8, count($faq) + 1));
    $features = old('features', $content['features']);
    $features = is_array($features) ? array_values($features) : [];
    $featureRows = max(4, min(12, count($features) + 2));
    $includes = old('preorder_includes', implode("\n", $content['preorder_includes']));
    $supportChannels = old('support_channels', $content['support_channels']);
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

            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">کارت‌های ویژگی</h2></div>
            <div class="card-body">
                <p class="form-text mt-0">هر کارت یک عنوان کوتاه و یک توضیح (اختیاری) دارد؛ حداکثر ۱۲ کارت. ردیف‌های بدون عنوان نادیده گرفته می‌شوند.</p>
                @error('features')<div class="alert alert-danger small py-2">{{ $message }}</div>@enderror

                @for ($i = 0; $i < $featureRows; $i++)
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <input
                                type="text" name="features[{{ $i }}][title]" maxlength="120"
                                value="{{ $features[$i]['title'] ?? '' }}"
                                placeholder="عنوان ویژگی {{ $i + 1 }}"
                                aria-label="عنوان ویژگی {{ $i + 1 }}"
                                @class(['form-control', 'is-invalid' => $errors->has("features.$i.title")])
                            >
                        </div>
                        <div class="col-md-8">
                            <textarea
                                name="features[{{ $i }}][description]" rows="1" maxlength="500"
                                placeholder="توضیح کوتاه"
                                aria-label="توضیح ویژگی {{ $i + 1 }}"
                                @class(['form-control', 'is-invalid' => $errors->has("features.$i.description")])
                            >{{ $features[$i]['description'] ?? '' }}</textarea>
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">متن بخش پیش‌خرید</h2></div>
            <div class="card-body">
                <p class="form-text mt-0">
                    مبلغ قابل پرداخت همیشه از <a href="{{ route('ideas.settings', $idea) }}">تنظیمات ایده</a> خوانده می‌شود
                    ({{ \App\Support\Money::formatRialAsToman($idea->preorder_amount_rial) }})؛ این متن‌ها فقط نمایشی‌اند.
                </p>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="preorder_title" class="form-label">عنوان بخش</label>
                        <input id="preorder_title" type="text" name="preorder_title" maxlength="120" placeholder="درخواست پیش‌خرید"
                            value="{{ old('preorder_title', $content['preorder_title']) }}" @class(['form-control', 'is-invalid' => $errors->has('preorder_title')])>
                        @error('preorder_title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="preorder_badge" class="form-label">نشان پیشنهاد</label>
                        <input id="preorder_badge" type="text" name="preorder_badge" maxlength="60" placeholder="۵۰٪ تخفیف پیش‌خرید"
                            value="{{ old('preorder_badge', $content['preorder_badge']) }}" @class(['form-control', 'is-invalid' => $errors->has('preorder_badge')])>
                        @error('preorder_badge')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="preorder_intro" class="form-label">متن معرفی</label>
                        <textarea id="preorder_intro" name="preorder_intro" rows="2" maxlength="1000"
                            placeholder="اگر به این محصول علاقه‌مند هستید، اطلاعات خود را وارد کنید تا به صفحه پرداخت هدایت شوید."
                            @class(['form-control', 'is-invalid' => $errors->has('preorder_intro')])>{{ old('preorder_intro', $content['preorder_intro']) }}</textarea>
                        @error('preorder_intro')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="preorder_original_price_toman" class="form-label">قیمت اصلی برای نمایش خط‌خورده (تومان)</label>
                        <input id="preorder_original_price_toman" type="text" inputmode="numeric" name="preorder_original_price_toman" maxlength="20"
                            value="{{ old('preorder_original_price_toman', $content['preorder_original_price_toman'] ? number_format($content['preorder_original_price_toman']) : '') }}"
                            @class(['form-control', 'ltr', 'is-invalid' => $errors->has('preorder_original_price_toman')])>
                        @error('preorder_original_price_toman')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">فقط اگر بیشتر از مبلغ پیش‌پرداخت باشد نمایش داده می‌شود.</div>
                    </div>
                    <div class="col-md-6">
                        <label for="preorder_button" class="form-label">متن دکمه پرداخت</label>
                        <input id="preorder_button" type="text" name="preorder_button" maxlength="60" placeholder="ثبت درخواست و ادامه به پرداخت"
                            value="{{ old('preorder_button', $content['preorder_button']) }}" @class(['form-control', 'is-invalid' => $errors->has('preorder_button')])>
                        @error('preorder_button')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="preorder_includes" class="form-label">چه می‌گیری؟ (هر خط یک مورد، حداکثر ۸)</label>
                        <textarea id="preorder_includes" name="preorder_includes" rows="4" maxlength="2000"
                            placeholder="۶ ماه اشتراک رایگان&#10;نصب و راه‌اندازی&#10;پشتیبانی اختصاصی"
                            @class(['form-control', 'is-invalid' => $errors->has('preorder_includes')])>{{ $includes }}</textarea>
                        @error('preorder_includes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="preorder_guarantee" class="form-label">ضمانت</label>
                        <textarea id="preorder_guarantee" name="preorder_guarantee" rows="4" maxlength="1000"
                            placeholder="اگر محصول عرضه نشد یا راضی نبودید، کل مبلغ بازگردانده می‌شود."
                            @class(['form-control', 'is-invalid' => $errors->has('preorder_guarantee')])>{{ old('preorder_guarantee', $content['preorder_guarantee']) }}</textarea>
                        @error('preorder_guarantee')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <div class="form-text">خلاصه شرایط بازپرداخت ثبت‌شده همیشه کنار آن نمایش داده می‌شود.</div>
                    </div>
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

                @unless ($landingPage->exists)
                    <input type="hidden" name="publish" value="0">
                    <div class="form-check form-switch mt-3 pt-3 border-top">
                        <input class="form-check-input" type="checkbox" role="switch" id="publish" name="publish" value="1" @checked(old('publish', true))>
                        <label class="form-check-label" for="publish">انتشار فوری</label>
                        <div class="form-text">خاموش: صفحه ساخته می‌شود، پیش‌نمایش می‌گیرید و بعد از صفحه ایده منتشرش می‌کنید.</div>
                    </div>
                @endunless

                @if ($blockers = $idea->preorderBlockers())
                    <div class="alert alert-warning small mt-3 mb-0">
                        فرم پیش‌خرید تا رفع این موارد نمایش داده نمی‌شود:
                        {{ implode(' ', $blockers) }}
                        <a href="{{ route('ideas.settings', $idea) }}">تنظیمات ایده</a>
                    </div>
                @endif
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">دمو</h2></div>
            <div class="card-body">
                <label for="demo_url" class="form-label">لینک دمو</label>
                <input id="demo_url" type="url" name="demo_url" maxlength="500" placeholder="https://demo.example.ir"
                    value="{{ old('demo_url', $content['demo_url']) }}" @class(['form-control', 'ltr', 'is-invalid' => $errors->has('demo_url')])>
                @error('demo_url')<div class="invalid-feedback">{{ $message }}</div>@enderror

                <label for="demo_label" class="form-label mt-2">متن دکمه</label>
                <input id="demo_label" type="text" name="demo_label" maxlength="60" placeholder="دیدن دمو"
                    value="{{ old('demo_label', $content['demo_label']) }}" @class(['form-control', 'is-invalid' => $errors->has('demo_label')])>

                <input type="hidden" name="demo_new_tab" value="0">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="demo_new_tab" name="demo_new_tab" value="1" @checked(old('demo_new_tab', $content['demo_new_tab']))>
                    <label class="form-check-label" for="demo_new_tab">باز شدن در زبانه جدید</label>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">ویدیوی پیش‌نمایش</h2></div>
            <div class="card-body">
                <label for="video_aparat" class="form-label">لینک ویدیو در آپارات (پیشنهادی)</label>
                <input id="video_aparat" type="text" name="video_aparat" maxlength="300" placeholder="https://www.aparat.com/v/abc123"
                    value="{{ old('video_aparat', $content['video_aparat'] ? 'https://www.aparat.com/v/' . $content['video_aparat'] : '') }}"
                    @class(['form-control', 'ltr', 'is-invalid' => $errors->has('video_aparat')])>
                @error('video_aparat')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">پخش از سرور آپارات؛ پهنای باند هاست مصرف نمی‌شود.</div>

                <label for="video_file" class="form-label mt-3">یا فایل MP4 (حداکثر {{ config('landing.video_max_mb') }} مگابایت)</label>
                @if ($videoUrl = $landingPage->videoUrl())
                    <video src="{{ $videoUrl }}" controls preload="none" class="w-100 rounded mb-2"></video>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="remove_video_file" name="remove_video_file" value="1">
                        <label class="form-check-label" for="remove_video_file">حذف ویدیوی فعلی</label>
                    </div>
                @endif
                <input type="file" name="video_file" id="video_file" accept="video/mp4" @class(['form-control', 'is-invalid' => $errors->has('video_file')])>
                @error('video_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">اگر هر دو باشد، آپارات نمایش داده می‌شود. تصویر اصلی، پوستر ویدیو است.</div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header"><h2 class="card-title h6 mb-0">پشتیبانی و اعتماد</h2></div>
            <div class="card-body">
                <label for="support_text" class="form-label">متن پشتیبانی این صفحه</label>
                <input id="support_text" type="text" name="support_text" maxlength="300" placeholder="سؤال دارید؟ در بله پیام دهید."
                    value="{{ old('support_text', $content['support_text']) }}" @class(['form-control', 'is-invalid' => $errors->has('support_text')])>

                <div class="form-text mb-2">راه‌های تماس مخصوص این صفحه؛ اگر خالی بماند، راه‌های <a href="{{ route('admin.settings.site') }}">تنظیمات سایت</a> نمایش داده می‌شود.</div>
                @for ($i = 0; $i < 4; $i++)
                    <div class="input-group input-group-sm mb-1">
                        <select name="support_channels[{{ $i }}][type]" class="form-select" style="max-width: 96px" aria-label="نوع راه تماس {{ $i + 1 }}">
                            <option value="">—</option>
                            @foreach (\App\Support\ContactChannel::TYPES as $type => $label)
                                <option value="{{ $type }}" @selected(($supportChannels[$i]['type'] ?? '') === $type)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <input type="text" name="support_channels[{{ $i }}][value]" maxlength="120" value="{{ $supportChannels[$i]['value'] ?? '' }}"
                            aria-label="شماره یا نام کاربری {{ $i + 1 }}" @class(['form-control', 'ltr', 'is-invalid' => $errors->has("support_channels.$i.value")])>
                        <input type="hidden" name="support_channels[{{ $i }}][label]" value="{{ $supportChannels[$i]['label'] ?? '' }}">
                    </div>
                    @error("support_channels.$i.value")<div class="text-danger small mb-1">{{ $message }}</div>@enderror
                @endfor

                <input type="hidden" name="show_trust" value="0">
                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="show_trust" name="show_trust" value="1" @checked(old('show_trust', $content['show_trust']))>
                    <label class="form-check-label" for="show_trust">بخش «ارائه‌شده توسط» با معرفی شرکت و نماد اعتماد</label>
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

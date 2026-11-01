<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $landingPage->title }}</title>

    <meta name="description" content="{{ $landingPage->description }}">
    <meta name="robots" content="{{ ($preview ?? false) ? 'noindex, nofollow' : 'index, follow' }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @if ($heroImageUrl = $landingPage->heroImageUrl())
        <meta property="og:image" content="{{ $heroImageUrl }}">
    @endif
    <meta property="og:title" content="{{ $landingPage->title }}">

    <style>
        @font-face {
            font-family: 'Vazirmatn';
            src: url('{{ asset('vendor/vazirmatn/Vazirmatn-Variable.woff2') }}') format('woff2-variations'),
                 url('{{ asset('vendor/vazirmatn/Vazirmatn-Variable.woff2') }}') format('woff2');
            font-weight: 100 900;
            font-display: swap;
        }

        :root {
            --primary: #4f46e5;
            --primary-dark: #3730a3;
            --text: #172033;
            --muted: #5b6475;
            --border: #dfe3ea;
            --surface: #ffffff;
            --background: #f5f7fb;
            --success-bg: #e8f8ee;
            --success-text: #176534;
            --error-bg: #fff0f0;
            --error-text: #a32020;
            --radius: 14px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Vazirmatn', Tahoma, Arial, sans-serif;
            background: var(--background);
            color: var(--text);
            line-height: 1.9;
            -webkit-text-size-adjust: 100%;
        }

        .container { width: min(100% - 32px, 960px); margin-inline: auto; }

        .hero {
            padding: clamp(48px, 9vw, 96px) 0;
            text-align: center;
            background: linear-gradient(135deg, #e0e7ff, var(--background));
        }

        .badge {
            display: inline-block;
            padding: 2px 16px;
            background: var(--surface);
            border-radius: 30px;
            font-size: 13px;
            color: var(--primary);
        }

        h1 {
            font-size: clamp(26px, 5vw, 44px);
            line-height: 1.6;
            margin: 20px 0;
        }

        h2 { font-size: clamp(20px, 3.4vw, 26px); line-height: 1.6; }

        .description {
            max-width: 650px;
            margin: 0 auto 28px;
            color: var(--muted);
            font-size: 17px;
        }

        .hero-image {
            display: block;
            max-width: 100%;
            height: auto;
            max-height: 420px;
            margin: 0 auto 28px;
            border-radius: var(--radius);
        }

        .cta, button[type="submit"] {
            display: inline-block;
            padding: 12px 32px;
            border: 0;
            border-radius: 10px;
            background: var(--primary);
            color: #fff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }

        .cta:hover, button[type="submit"]:hover { background: var(--primary-dark); }

        .cta:focus-visible, button:focus-visible, input:focus-visible, summary:focus-visible, a:focus-visible {
            outline: 3px solid #a5b4fc;
            outline-offset: 2px;
        }

        .content { padding: 48px 0 16px; text-align: center; }

        .body-text { max-width: 720px; margin: 0 auto; color: var(--muted); }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            padding: 0;
            margin: 32px 0;
            list-style: none;
        }

        .features li {
            padding: 14px 18px;
            background: var(--surface);
            border-radius: var(--radius);
        }

        .faq { max-width: 720px; margin: 40px auto; text-align: right; }

        .faq details {
            margin-bottom: 10px;
            padding: 12px 18px;
            background: var(--surface);
            border-radius: var(--radius);
        }

        .faq summary { cursor: pointer; font-weight: 700; }

        .form-card {
            max-width: 560px;
            margin: 32px auto;
            padding: clamp(20px, 4vw, 32px);
            background: var(--surface);
            border-radius: var(--radius);
            box-shadow: 0 1px 3px rgba(23, 32, 51, .06);
            text-align: right;
        }

        .form-card h2 { margin-top: 0; }

        .form-intro { color: var(--muted); }

        .form-group { margin-bottom: 16px; }

        .form-group > label:not(.check) { display: block; margin-bottom: 6px; font-weight: 500; }

        .form-group input:not([type="checkbox"]) {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font: inherit;
            background: #fff;
        }

        .form-group input[aria-invalid="true"] { border-color: var(--error-text); }

        .field-error { margin-top: 4px; color: var(--error-text); font-size: 14px; }

        .check { display: flex; gap: 10px; align-items: flex-start; cursor: pointer; }

        .check input { margin-top: 7px; flex-shrink: 0; width: 18px; height: 18px; }

        .ltr-input { direction: ltr; text-align: right; }

        .form-alert { padding: 12px 16px; margin-bottom: 16px; border-radius: 10px; }

        .form-alert ul { margin: 0; padding-inline-start: 20px; }

        .form-alert.success { background: var(--success-bg); color: var(--success-text); }

        .form-alert.error { background: var(--error-bg); color: var(--error-text); }

        .preorder-amount { font-size: 18px; }

        .refund-terms {
            margin-bottom: 16px;
            padding: 12px 16px;
            max-height: 220px;
            overflow-y: auto;
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
        }

        .hero-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 10px; }

        .cta-secondary { background: var(--surface); color: var(--primary); border: 1px solid #c7d2fe; }

        .cta-secondary:hover { background: #eef2ff; }

        .media { max-width: 760px; margin: 0 auto 28px; }

        .video-frame { position: relative; aspect-ratio: 16 / 9; border-radius: var(--radius); overflow: hidden; background: #111827; }

        .video-frame iframe, .video-frame video { position: absolute; inset: 0; width: 100%; height: 100%; border: 0; }

        .video-play { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; width: 100%; border: 0; background: #1e1b4b center / cover no-repeat; color: #fff; font: inherit; font-weight: 700; cursor: pointer; }

        .video-play::before { content: ''; width: 64px; height: 64px; border-radius: 50%; background: rgba(255, 255, 255, .92) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'%3E%3Cpath fill='%234f46e5' d='M8 5v14l11-7z'/%3E%3C/svg%3E") center / 30px no-repeat; }

        .video-play span { padding: 2px 12px; border-radius: 20px; background: rgba(0, 0, 0, .45); }

        .feature-card h3 { margin: 0 0 4px; font-size: 16px; }

        .feature-card p { margin: 0; color: var(--muted); font-size: 14px; }

        .offer-badge { display: inline-block; padding: 2px 14px; margin-bottom: 8px; border-radius: 20px; background: #fef3c7; color: #92400e; font-weight: 700; font-size: 14px; }

        .price-old { display: inline-block; margin-inline-start: 8px; color: var(--muted); text-decoration: line-through; font-size: 15px; white-space: nowrap; }

        .includes { margin: 0 0 16px; padding: 0; list-style: none; }

        .includes li { position: relative; padding-inline-start: 26px; margin-bottom: 4px; }

        .includes li::before { content: '✓'; position: absolute; inset-inline-start: 0; color: var(--success-text); font-weight: 700; }

        .guarantee { margin-bottom: 16px; padding: 12px 16px; border-radius: 10px; background: var(--success-bg); color: var(--success-text); }

        .assurance { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px; max-width: 960px; margin: 32px auto; text-align: right; }

        .assurance > div { padding: 20px; background: var(--surface); border-radius: var(--radius); }

        .assurance h2 { margin: 0 0 8px; font-size: 18px; }

        .channels { display: flex; flex-wrap: wrap; gap: 8px; margin: 10px 0 0; padding: 0; list-style: none; }

        .channels a, .channels span { display: inline-block; padding: 6px 14px; border-radius: 20px; background: #eef2ff; color: var(--primary-dark); text-decoration: none; }

        .trust-body { display: flex; gap: 14px; align-items: flex-start; }

        .trust-body img.logo { max-height: 48px; max-width: 120px; object-fit: contain; }

        .trust-body .seal img { width: 88px; height: auto; }

        .notify-alt { margin: 16px 0 0; text-align: center; font-size: 15px; }

        .notify-alt a { color: var(--primary); }

        .hero .media { margin-top: 28px; }

        /* متن قالب‌دار ویرایشگر (نسخه ۱.۱.۱) */
        .rich-text { text-align: start; overflow-wrap: anywhere; }

        .rich-text > :first-child { margin-top: 0; }

        .rich-text > :last-child { margin-bottom: 0; }

        .rich-text h1, .rich-text h2, .rich-text h3, .rich-text h4, .rich-text h5, .rich-text h6 { margin: 1.2em 0 .4em; line-height: 1.6; color: var(--text); }

        .rich-text h1 { font-size: 1.6em; }
        .rich-text h2 { font-size: 1.4em; }
        .rich-text h3 { font-size: 1.25em; }
        .rich-text h4 { font-size: 1.1em; }
        .rich-text h5 { font-size: 1em; }
        .rich-text h6 { font-size: .9em; color: var(--muted); }

        .rich-text p { margin: 0 0 .8em; }

        .rich-text ul, .rich-text ol { margin: 0 0 .8em; padding-inline-start: 1.6em; }

        .rich-text li { margin-bottom: .25em; }

        .rich-text blockquote { margin: 1em 0; padding: .6em 1em; border-inline-start: 4px solid #c7d2fe; background: #eef2ff; border-radius: 8px; color: var(--text); }

        .rich-text hr { margin: 1.4em 0; border: 0; border-top: 1px solid var(--border); }

        .rich-text a { color: var(--primary); }

        .body-text.rich-text { color: var(--text); }

        .preview-banner { position: sticky; top: 0; z-index: 10; padding: 8px 16px; background: #fef3c7; color: #92400e; text-align: center; font-size: 14px; }

        @media (prefers-reduced-motion: no-preference) {
            html { scroll-behavior: smooth; }
        }
    </style>
</head>

<body>
    @if ($preview ?? false)
        <div class="preview-banner" role="status">
            پیش‌نمایش برای مدیر — {{ $landingPage->is_active ? 'این صفحه منتشر شده است' : 'این صفحه هنوز منتشر نشده و برای عموم نمایش داده نمی‌شود' }}.
            بازدید و کلیک‌ها ثبت نمی‌شوند.
        </div>
    @endif

    @php
        $content = $landingPage->contentData();
        $refundPolicy = $landingPage->idea->currentRefundPolicy;
        $preorderOpen = $content['show_preorder_form'] && $landingPage->idea->acceptsPreorders();
        $privacyUrl = config('app.privacy_url');
        $preview = $preview ?? false;
        $fa = fn ($value) => \App\Support\Format::faDigits($value);
        $site = app(\App\Services\SiteSettings::class);
        $company = $site->get('company');
        $enamad = $site->enamad();
        $supportChannels = $landingPage->supportChannels();
        $supportText = $content['support_text'] ?? $site->get('support')['help_text'];
        $showTrust = $content['show_trust'] && ($company['name'] || $enamad);
        $aparatUrl = $landingPage->aparatEmbedUrl();
        $videoUrl = $aparatUrl ? null : $landingPage->videoUrl();
        $amountRial = $landingPage->idea->preorder_amount_rial;
        $originalPrice = $content['preorder_original_price_toman'];
        $showOriginalPrice = $originalPrice && $amountRial && $originalPrice > \App\Support\Money::rialToToman($amountRial);

        // مقدار قبلی فقط به فرمی برمی‌گردد که ارسال شده بود
        $oldFor = fn (string $form, string $field) => old('form') === $form ? old($field) : null;

        $leadErrors = $errors->getBag('lead');
        $preorderErrors = $errors->getBag('preorder');
    @endphp

    <main>
        <section class="hero">
            <div class="container">
                <span class="badge">{{ $fa($landingPage->idea->name) }}</span>

                <h1>{{ $fa($landingPage->title) }}</h1>

                @if ($landingPage->description)
                    <p class="description">{{ $fa($landingPage->description) }}</p>
                @endif

                @php
                    // ترتیب پیشنهادی: پیش‌خرید اول؛ اگر بسته است، «خبرم کن» دکمه اصلی است
                    $primaryHref = $preorderOpen ? '#preorder-form' : ($content['show_lead_form'] ? '#lead-form' : null);
                    $primaryText = $landingPage->cta_text ?: ($preorderOpen ? 'پیش‌خرید' : 'خبرم کن');
                @endphp

                <div class="hero-actions">
                    @if ($primaryHref)
                        <a href="{{ $primaryHref }}" class="cta" id="main-cta" data-track="cta_click">{{ $fa($primaryText) }}</a>
                    @endif

                    @if ($content['demo_url'])
                        <a href="{{ $content['demo_url'] }}" class="cta cta-secondary" data-track="demo_click"
                            @if ($content['demo_new_tab']) target="_blank" rel="noopener" @endif>
                            {{ $fa($content['demo_label'] ?: 'دیدن دمو') }}
                        </a>
                    @elseif ($preorderOpen && $content['show_lead_form'])
                        <a href="#lead-form" class="cta cta-secondary" data-track="notify_click">وقتی آماده شد خبرم کن</a>
                    @endif
                </div>

                @if ($aparatUrl || $videoUrl)
                    <div class="media">
                        <div class="video-frame">
                            @if ($aparatUrl)
                                {{-- iframe آپارات فقط پس از کلیک بارگذاری می‌شود (سرعت صفحه و ثبت پخش) --}}
                                <button type="button" class="video-play" data-video-src="{{ $aparatUrl }}"
                                    @if ($heroImageUrl) style="background-image: url('{{ $heroImageUrl }}')" @endif>
                                    <span>پخش ویدیوی معرفی</span>
                                </button>
                            @else
                                <video src="{{ $videoUrl }}" controls preload="none" playsinline @if ($heroImageUrl) poster="{{ $heroImageUrl }}" @endif data-track-play></video>
                            @endif
                        </div>
                    </div>
                @elseif ($heroImageUrl)
                    <img src="{{ $heroImageUrl }}" alt="{{ $landingPage->title }}" class="hero-image">
                @endif

            </div>
        </section>

        <section class="content container">
            <h2>{{ $fa($content['body_title'] ?? 'به‌زودی اطلاعات بیشتری منتشر می‌شود') }}</h2>

            @if ($content['body_text'])
                <div class="body-text rich-text">{!! \App\Support\RichText::render($content['body_text']) !!}</div>
            @else
                <p class="body-text">
                    اگر به این محصول علاقه‌مند هستید،
                    می‌توانید برای دریافت اطلاعات بیشتر ثبت‌نام کنید.
                </p>
            @endif

            @if ($content['features'])
                <ul class="features">
                    @foreach ($content['features'] as $feature)
                        <li class="feature-card">
                            <h3>{{ $fa($feature['title']) }}</h3>
                            @if ($feature['description'])
                                <p>{{ $fa($feature['description']) }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if ($preorderOpen)
            <section id="preorder-form" class="form-card container preorder-section">
                @if ($content['preorder_badge'])
                    <span class="offer-badge">{{ $fa($content['preorder_badge']) }}</span>
                @endif

                <h2>{{ $fa($content['preorder_title'] ?: 'درخواست پیش‌خرید') }}</h2>

                @if ($content['preorder_intro'])
                    <div class="form-intro rich-text">{!! \App\Support\RichText::render($content['preorder_intro']) !!}</div>
                @else
                    <p class="form-intro">اگر به این محصول علاقه‌مند هستید، اطلاعات خود را وارد کنید تا به صفحه پرداخت هدایت شوید.</p>
                @endif

                @if ($content['preorder_includes'])
                    <ul class="includes" aria-label="چه می‌گیرید">
                        @foreach ($content['preorder_includes'] as $item)
                            <li>{{ $fa($item) }}</li>
                        @endforeach
                    </ul>
                @endif

                <p class="preorder-amount">
                    مبلغ پیش‌پرداخت:
                    <strong>{{ $fa(\App\Support\Money::formatRialAsToman($amountRial)) }}</strong>
                    @if ($showOriginalPrice)
                        <del class="price-old" aria-label="قیمت اصلی">{{ $fa(number_format($originalPrice) . ' تومان') }}</del>
                    @endif
                </p>

                @if ($content['preorder_guarantee'])
                    <div class="guarantee rich-text">{!! \App\Support\RichText::render($content['preorder_guarantee']) !!}</div>
                @endif

                @if ($preorderErrors->any())
                    <div class="form-alert error" role="alert">
                        لطفاً موارد مشخص‌شده را اصلاح کنید.
                        {{-- خطای فیلدهای متنی زیر خود فیلد نمایش داده می‌شود --}}
                        @php($otherErrors = collect($preorderErrors->messages())->except(['name', 'phone'])->flatten())
                        @if ($otherErrors->isNotEmpty())
                            <ul>
                                @foreach ($otherErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <form method="POST" action="{{ route('preorders.store', ['slug' => $landingPage->slug]) }}">
                    @csrf
                    <x-public-form-guard id="preorder" />
                    <input type="hidden" name="form" value="preorder">
                    <input type="hidden" name="refund_policy_version" value="{{ $refundPolicy->version }}">

                    <div class="form-group">
                        <label for="preorder-name">نام و نام خانوادگی</label>
                        <input id="preorder-name" type="text" name="name" maxlength="120" value="{{ $oldFor('preorder', 'name') }}"
                            autocomplete="name" required
                            @if ($preorderErrors->has('name')) aria-invalid="true" aria-describedby="preorder-name-error" @endif>
                        @if ($preorderErrors->has('name'))
                            <div class="field-error" id="preorder-name-error">{{ $preorderErrors->first('name') }}</div>
                        @endif
                    </div>

                    <div class="form-group">
                        <label for="preorder-phone">شماره موبایل</label>
                        <input id="preorder-phone" type="tel" name="phone" maxlength="30" value="{{ $oldFor('preorder', 'phone') }}"
                            autocomplete="tel" inputmode="tel" placeholder="09123456789" class="ltr-input" required
                            @if ($preorderErrors->has('phone')) aria-invalid="true" aria-describedby="preorder-phone-error" @endif>
                        @if ($preorderErrors->has('phone'))
                            <div class="field-error" id="preorder-phone-error">{{ $preorderErrors->first('phone') }}</div>
                        @endif
                    </div>

                    <div class="form-group">
                        <label class="check">
                            <input type="checkbox" name="consent" value="1" required @checked($oldFor('preorder', 'consent'))>
                            <span>با ثبت اطلاعات تماس و پیگیری درخواست پیش‌خرید موافقم.</span>
                        </label>
                    </div>

                    <div class="refund-terms" id="refund-terms" tabindex="0">
                        <strong>شرایط بازپرداخت: {{ $fa($refundPolicy->summary()) }}</strong>
                        <p>{!! nl2br(e($fa($refundPolicy->terms))) !!}</p>
                    </div>

                    <div class="form-group">
                        <label class="check">
                            <input type="checkbox" name="accept_terms" value="1" required aria-describedby="refund-terms"
                                @checked($oldFor('preorder', 'accept_terms'))>
                            <span>شرایط بازپرداخت را خواندم و می‌پذیرم.</span>
                        </label>
                    </div>

                    <button type="submit">{{ $fa($content['preorder_button'] ?: 'ثبت درخواست و ادامه به پرداخت') }}</button>
                </form>

                @if ($content['show_lead_form'])
                    <p class="notify-alt">
                        <a href="#lead-form" data-track="notify_click">فعلاً پیش‌پرداخت نمی‌کنم؛ وقتی آماده شد خبرم کن</a>
                    </p>
                @endif
            </section>
        @endif
        @if ($content['faq'])
            <section class="content container">
                <div class="faq">
                    <h2>پرسش‌های متداول</h2>
                    @foreach ($content['faq'] as $item)
                        <details>
                            <summary>{{ $fa($item['q']) }}</summary>
                            <div class="rich-text">{!! \App\Support\RichText::render($item['a']) !!}</div>
                        </details>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($content['show_lead_form'])
            <section id="lead-form" class="form-card container">
                <h2>وقتی آماده شد خبرم کن</h2>

                @if (session('lead_success') === $landingPage->slug)
                    <div class="form-alert success" role="status">
                        اطلاعات شما با موفقیت ثبت شد.
                        وقتی {{ $fa($landingPage->idea->name) }} آماده شد، با پیامک خبرتان می‌کنیم.
                    </div>
                @else
                    <p class="form-intro">نام و شماره موبایلتان را بگذارید؛ بدون پرداخت، فقط وقتی {{ $fa($landingPage->idea->name) }} آماده شد پیامک می‌گیرید.</p>

                    @if ($leadErrors->any())
                        <div class="form-alert error" role="alert">
                            لطفاً موارد مشخص‌شده را اصلاح کنید.
                        {{-- خطای فیلدهای متنی زیر خود فیلد نمایش داده می‌شود --}}
                        @php($otherErrors = collect($leadErrors->messages())->except(['name', 'phone'])->flatten())
                        @if ($otherErrors->isNotEmpty())
                            <ul>
                                @foreach ($otherErrors as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                        </div>
                    @endif

                    <form action="{{ route('leads.store', $landingPage->slug) }}" method="POST">
                        @csrf
                        <x-public-form-guard id="lead" />
                        <input type="hidden" name="form" value="lead">
                        <input type="hidden" name="intent" value="{{ \App\Models\Lead::INTENT_NOTIFY }}">

                        <div class="form-group">
                            <label for="lead-name">نام</label>
                            <input id="lead-name" type="text" name="name" value="{{ $oldFor('lead', 'name') }}"
                                maxlength="120" autocomplete="name" required
                                @if ($leadErrors->has('name')) aria-invalid="true" aria-describedby="lead-name-error" @endif>
                            @if ($leadErrors->has('name'))
                                <div class="field-error" id="lead-name-error">{{ $leadErrors->first('name') }}</div>
                            @endif
                        </div>

                        <div class="form-group">
                            <label for="lead-phone">شماره موبایل</label>
                            <input id="lead-phone" type="tel" name="phone" value="{{ $oldFor('lead', 'phone') }}"
                                maxlength="30" autocomplete="tel" inputmode="tel" placeholder="09123456789" class="ltr-input" required
                                @if ($leadErrors->has('phone')) aria-invalid="true" aria-describedby="lead-phone-error" @endif>
                            @if ($leadErrors->has('phone'))
                                <div class="field-error" id="lead-phone-error">{{ $leadErrors->first('phone') }}</div>
                            @endif
                        </div>

                        <div class="form-group">
                            <label class="check">
                                <input type="checkbox" name="consent" value="1" required @checked($oldFor('lead', 'consent'))>
                                <span>
                                    موافقم برای پیگیری درخواست و اطلاع‌رسانی مرتبط با این محصول با من تماس گرفته شود.
                                    @if ($privacyUrl)
                                        <a href="{{ $privacyUrl }}" target="_blank" rel="noopener">سیاست حریم خصوصی</a>
                                    @endif
                                </span>
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="check">
                                <input type="checkbox" name="marketing_consent" value="1" @checked($oldFor('lead', 'marketing_consent'))>
                                <span>مایلم پیامک‌های معرفی محصولات و پیشنهادهای جدید را هم دریافت کنم (اختیاری، قابل لغو).</span>
                            </label>
                        </div>

                        <button type="submit">خبرم کن</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($supportChannels || $supportText || $showTrust)
            <section class="assurance container" aria-label="پشتیبانی و ارائه‌دهنده">
                @if ($supportChannels || $supportText)
                    <div>
                        <h2>پشتیبانی</h2>
                        @if ($supportText)
                            <p style="margin: 0">{{ $fa($supportText) }}</p>
                        @endif
                        @if ($supportChannels)
                            <ul class="channels">
                                @foreach ($supportChannels as $channel)
                                    <li>
                                        @if ($url = $channel->url())
                                            <a href="{{ $url }}" @if (str_starts_with($url, 'http')) target="_blank" rel="noopener" @endif>{{ $channel->name() }}@if ($channel->display() !== ''): <bdi dir="ltr">{{ $fa($channel->display()) }}</bdi>@endif</a>
                                        @else
                                            <span>{{ $channel->name() }}@if ($channel->display() !== ''): <bdi dir="ltr">{{ $fa($channel->display()) }}</bdi>@endif</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                @if ($showTrust)
                    <div>
                        <h2>ارائه‌دهنده</h2>
                        <div class="trust-body">
                            @if ($logoUrl = $site->logoUrl())
                                <img src="{{ $logoUrl }}" alt="{{ $company['name'] }}" class="logo" loading="lazy">
                            @endif
                            <div style="flex: 1">
                                @if ($company['name'])
                                    <p style="margin: 0">
                                        این محصول توسط
                                        @if ($company['website'])
                                            <a href="{{ $company['website'] }}" target="_blank" rel="noopener"><strong>{{ $company['name'] }}</strong></a>
                                        @else
                                            <strong>{{ $company['name'] }}</strong>
                                        @endif
                                        ارائه می‌شود.
                                    </p>
                                @endif
                                @if ($company['intro'])
                                    <p style="margin: 4px 0 0; color: var(--muted); font-size: 14px">{{ $fa($company['intro']) }}</p>
                                @endif
                            </div>
                            @if ($enamad)
                                <a class="seal" href="{{ $enamad['url'] }}" target="_blank" referrerpolicy="origin" rel="noopener">
                                    <img src="{{ $enamad['image'] }}" alt="نماد اعتماد الکترونیکی" referrerpolicy="origin" code="{{ $enamad['code'] }}" loading="lazy" style="cursor: pointer">
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </section>
        @endif
    </main>

    {{-- پشتیبانی و شرکت همین بالا آمده‌اند؛ پاورقی تکرارشان نمی‌کند --}}
    <x-site-footer :support="false" :company="! $showTrust" />

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // پس از خطا یا پیام موفقیت، فرم مربوط دیده شود
        const alert = document.querySelector('.form-card .form-alert');

        if (alert) {
            alert.closest('.form-card').scrollIntoView({ block: 'start' });
        }

        const tracking = @json(! $preview);
        const url = @json(route('landing-pages.events.store', ['slug' => $landingPage->slug]));
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

        function track(eventName) {
            if (!tracking || !token) {
                return;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ event_name: eventName }),
                keepalive: true
            }).catch(function () {
                // شکست رهگیری نباید مانع استفاده از صفحه شود.
            });
        }

        document.querySelectorAll('[data-track]').forEach(function (element) {
            element.addEventListener('click', function () {
                track(element.dataset.track);
            });
        });

        // پس از پرش به یک فرم، اولین فیلد آن فوکوس می‌گیرد (دسترس‌پذیری، مرحله ۱۳۵)
        document.querySelectorAll('a[href="#lead-form"], a[href="#preorder-form"]').forEach(function (link) {
            link.addEventListener('click', function () {
                // فیلد ضدربات (tabindex=-1) نباید فوکوس بگیرد
                const field = document.querySelector(link.getAttribute('href') + ' input:not([type="hidden"]):not([tabindex="-1"])');

                if (field) {
                    setTimeout(function () { field.focus({ preventScroll: true }); }, 450);
                }
            });
        });

        // ویدیوی آپارات: iframe فقط با کلیک کاربر ساخته می‌شود
        document.querySelectorAll('[data-video-src]').forEach(function (button) {
            button.addEventListener('click', function () {
                const frame = document.createElement('iframe');
                frame.src = button.dataset.videoSrc;
                frame.title = 'ویدیوی معرفی';
                frame.allow = 'autoplay; fullscreen; picture-in-picture';
                frame.allowFullscreen = true;
                button.replaceWith(frame);
                track('video_play');
            }, { once: true });
        });

        document.querySelectorAll('video[data-track-play]').forEach(function (video) {
            video.addEventListener('play', function () {
                track('video_play');
            }, { once: true });
        });
    });
    </script>
</body>
</html>

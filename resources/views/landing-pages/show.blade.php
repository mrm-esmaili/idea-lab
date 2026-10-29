<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $landingPage->title }}</title>

    <meta name="description" content="{{ $landingPage->description }}">
    <meta name="robots" content="index, follow">
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

        .footer { padding: 24px 0; text-align: center; color: var(--muted); font-size: 13px; }

        .footer a { color: inherit; }

        @media (prefers-reduced-motion: no-preference) {
            html { scroll-behavior: smooth; }
        }
    </style>
</head>

<body>
    @php
        $content = $landingPage->contentData();
        $refundPolicy = $landingPage->idea->currentRefundPolicy;
        $preorderOpen = $content['show_preorder_form'] && $landingPage->idea->acceptsPreorders();
        $privacyUrl = config('app.privacy_url');

        // مقدار قبلی فقط به فرمی برمی‌گردد که ارسال شده بود
        $oldFor = fn (string $form, string $field) => old('form') === $form ? old($field) : null;

        $leadErrors = $errors->getBag('lead');
        $preorderErrors = $errors->getBag('preorder');
    @endphp

    <main>
        <section class="hero">
            <div class="container">
                <span class="badge">{{ $landingPage->idea->name }}</span>

                <h1>{{ $landingPage->title }}</h1>

                @if ($landingPage->description)
                    <p class="description">{{ $landingPage->description }}</p>
                @endif

                @if ($heroImageUrl)
                    <img src="{{ $heroImageUrl }}" alt="{{ $landingPage->title }}" class="hero-image">
                @endif

                @if ($landingPage->cta_text && ($content['show_lead_form'] || $preorderOpen))
                    <a href="{{ $content['show_lead_form'] ? '#lead-form' : '#preorder-form' }}" class="cta" id="main-cta">
                        {{ $landingPage->cta_text }}
                    </a>
                @endif
            </div>
        </section>

        <section class="content container">
            <h2>{{ $content['body_title'] ?? 'به‌زودی اطلاعات بیشتری منتشر می‌شود' }}</h2>

            @if ($content['body_text'])
                <p class="body-text">{!! nl2br(e($content['body_text'])) !!}</p>
            @else
                <p class="body-text">
                    اگر به این محصول علاقه‌مند هستید،
                    می‌توانید برای دریافت اطلاعات بیشتر ثبت‌نام کنید.
                </p>
            @endif

            @if ($content['features'])
                <ul class="features">
                    @foreach ($content['features'] as $feature)
                        <li>{{ $feature }}</li>
                    @endforeach
                </ul>
            @endif

            @if ($content['faq'])
                <div class="faq">
                    <h2>پرسش‌های متداول</h2>
                    @foreach ($content['faq'] as $item)
                        <details>
                            <summary>{{ $item['q'] }}</summary>
                            <p>{!! nl2br(e($item['a'])) !!}</p>
                        </details>
                    @endforeach
                </div>
            @endif
        </section>

        @if ($content['show_lead_form'])
            <section id="lead-form" class="form-card container">
                <h2>دریافت اطلاعات و ثبت علاقه‌مندی</h2>

                @if (session('lead_success') === $landingPage->slug)
                    <div class="form-alert success" role="status">
                        اطلاعات شما با موفقیت ثبت شد.
                        در صورت نیاز به پیگیری، از طریق اطلاعات تماس ارائه‌شده با شما ارتباط خواهیم گرفت.
                    </div>
                @else
                    @if ($leadErrors->any())
                        <div class="form-alert error" role="alert">
                            لطفاً موارد مشخص‌شده را اصلاح کنید.
                        {{-- خطای فیلدهای متنی زیر خود فیلد نمایش داده می‌شود --}}
                        @php($otherErrors = collect($leadErrors->messages())->except(['name', 'phone', 'email'])->flatten())
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
                            <label for="lead-email">ایمیل (اختیاری)</label>
                            <input id="lead-email" type="email" name="email" value="{{ $oldFor('lead', 'email') }}"
                                maxlength="255" autocomplete="email" class="ltr-input"
                                @if ($leadErrors->has('email')) aria-invalid="true" aria-describedby="lead-email-error" @endif>
                            @if ($leadErrors->has('email'))
                                <div class="field-error" id="lead-email-error">{{ $leadErrors->first('email') }}</div>
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

                        <button type="submit">ثبت علاقه‌مندی</button>
                    </form>
                @endif
            </section>
        @endif

        @if ($preorderOpen)
            <section id="preorder-form" class="form-card container preorder-section">
                <h2>درخواست پیش‌خرید</h2>

                <p class="form-intro">
                    اگر به این محصول علاقه‌مند هستید، اطلاعات خود را وارد کنید تا به صفحه پرداخت هدایت شوید.
                </p>

                <p class="preorder-amount">
                    مبلغ پیش‌پرداخت:
                    <strong>{{ \App\Support\Money::formatRialAsToman($landingPage->idea->preorder_amount_rial) }}</strong>
                </p>

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
                        <strong>شرایط بازپرداخت: {{ $refundPolicy->summary() }}</strong>
                        <p>{!! nl2br(e($refundPolicy->terms)) !!}</p>
                    </div>

                    <div class="form-group">
                        <label class="check">
                            <input type="checkbox" name="accept_terms" value="1" required aria-describedby="refund-terms"
                                @checked($oldFor('preorder', 'accept_terms'))>
                            <span>شرایط بازپرداخت را خواندم و می‌پذیرم.</span>
                        </label>
                    </div>

                    <button type="submit">ثبت درخواست و ادامه به پرداخت</button>
                </form>
            </section>
        @endif
    </main>

    <footer class="footer">
        <div class="container">
            ساخته‌شده با سیستم اعتبارسنجی ایده
            — <a href="{{ route('account.login') }}">ورود به حساب کاربری</a>
            @if ($privacyUrl)
                — <a href="{{ $privacyUrl }}" target="_blank" rel="noopener">حریم خصوصی</a>
            @endif
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        // پس از خطا یا پیام موفقیت، فرم مربوط دیده شود
        const alert = document.querySelector('.form-card .form-alert');

        if (alert) {
            alert.closest('.form-card').scrollIntoView({ block: 'start' });
        }

        const cta = document.getElementById('main-cta');

        if (!cta) {
            return;
        }

        cta.addEventListener('click', function () {
            const url = @json(route('landing-pages.events.store', ['slug' => $landingPage->slug]));
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            if (!token) {
                return;
            }

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token
                },
                body: JSON.stringify({ event_name: 'cta_click' }),
                keepalive: true
            }).catch(function () {
                // شکست رهگیری نباید مانع استفاده از صفحه شود.
            });
        });
    });
    </script>
</body>
</html>

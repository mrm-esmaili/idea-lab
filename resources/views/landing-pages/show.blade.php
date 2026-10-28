<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $landingPage->title }}</title>

    <meta
        name="description"
        content="{{ $landingPage->description }}"
    >

    <meta name="robots" content="index, follow">

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Tahoma, Arial, sans-serif;
            background: #f5f7fb;
            color: #172033;
            line-height: 2;
        }

        .container {
            width: min(100% - 32px, 960px);
            margin: auto;
        }

        .hero {
            padding: 90px 0;
            text-align: center;
            background: linear-gradient(135deg, #e0e7ff, #f5f7fb);
        }

        .badge {
            display: inline-block;
            padding: 4px 16px;
            background: #fff;
            border-radius: 30px;
            font-size: 13px;
            color: #4338ca;
        }

        h1 {
            font-size: clamp(28px, 5vw, 46px);
            line-height: 1.7;
            margin: 24px 0;
        }

        .description {
            max-width: 650px;
            margin: 0 auto 30px;
            color: #475569;
            font-size: 17px;
        }

        .cta {
            display: inline-block;
            padding: 12px 32px;
            border-radius: 10px;
            background: #4f46e5;
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .cta:hover {
            background: #3730a3;
        }

        .content {
            padding: 50px 0;
            text-align: center;
        }

        .footer {
            padding: 24px 0;
            text-align: center;
            color: #64748b;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            .hero {
                padding: 55px 0;
            }
        }

        .preorder-section {
            max-width: 560px;
            margin: 40px auto;
            padding: 24px;
            background: #fff;
            border-radius: 12px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
        }

        .form-group input:not([type="checkbox"]) {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        .preorder-section button {
            padding: 12px 20px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
        }

        .success-message {
            padding: 12px;
            margin-bottom: 16px;
            background: #e8f8ee;
            color: #176534;
            border-radius: 8px;
        }

        .error-message {
            padding: 12px;
            margin-bottom: 16px;
            background: #fff0f0;
            color: #a32020;
            border-radius: 8px;
        }

        .hero-image {
            display: block;
            max-width: 100%;
            max-height: 420px;
            margin: 0 auto 30px;
            border-radius: 12px;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            padding: 0;
            margin: 30px 0;
            list-style: none;
        }

        .features li {
            padding: 14px 18px;
            background: #fff;
            border-radius: 10px;
        }

        .body-text {
            max-width: 720px;
            margin: 0 auto;
        }

        .faq {
            max-width: 720px;
            margin: 40px auto;
            text-align: right;
        }

        .faq details {
            margin-bottom: 10px;
            padding: 12px 18px;
            background: #fff;
            border-radius: 10px;
        }

        .faq summary {
            cursor: pointer;
            font-weight: bold;
        }
    </style>
</head>

<body>
    @php($content = $landingPage->contentData())

    <main>
        <section class="hero">
            <div class="container">

                <span class="badge">
                    {{ $landingPage->idea->name }}
                </span>

                <h1>
                    {{ $landingPage->title }}
                </h1>

                @if ($landingPage->description)
                    <p class="description">
                        {{ $landingPage->description }}
                    </p>
                @endif

                @if ($heroImageUrl = $landingPage->heroImageUrl())
                    <img
                        src="{{ $heroImageUrl }}"
                        alt="{{ $landingPage->title }}"
                        class="hero-image"
                    >
                @endif

                @if ($landingPage->cta_text && ($content['show_lead_form'] || $content['show_preorder_form']))
                    <a
                        href="{{ $content['show_lead_form'] ? '#lead-form' : '#preorder-form' }}"
                        class="cta"
                        id="main-cta"
                    >
                        {{ $landingPage->cta_text }}
                    </a>
                @endif

            </div>
        </section>

        <section class="content container">

            <h2>
                {{ $content['body_title'] ?? 'به‌زودی اطلاعات بیشتری منتشر می‌شود' }}
            </h2>

            @if ($content['body_text'])
                <p class="body-text">{!! nl2br(e($content['body_text'])) !!}</p>
            @else
                <p>
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

            @if ($content['show_lead_form'])
            <div id="lead-form">

                <h2>دریافت اطلاعات و ثبت علاقه‌مندی</h2>

                @if (session('lead_success'))
                    <div role="status">
                        اطلاعات شما با موفقیت ثبت شد.
                        در صورت نیاز به پیگیری، از طریق اطلاعات تماس
                        ارائه‌شده با شما ارتباط خواهیم گرفت.
                    </div>
                @else

                    @if ($errors->any())
                        <div role="alert">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form
                        action="{{ route('leads.store', $landingPage->slug) }}"
                        method="POST"
                    >
                        @csrf

                        <div>
                            <label for="lead-name">نام</label>
                            <input
                                id="lead-name"
                                type="text"
                                name="name"
                                value="{{ old('name') }}"
                                maxlength="120"
                                autocomplete="name"
                                required
                            >
                        </div>

                        <div>
                            <label for="lead-phone">شماره تماس</label>
                            <input
                                id="lead-phone"
                                type="tel"
                                name="phone"
                                value="{{ old('phone') }}"
                                maxlength="30"
                                autocomplete="tel"
                                required
                            >
                        </div>

                        <div>
                            <label for="lead-email">
                                ایمیل (اختیاری)
                            </label>
                            <input
                                id="lead-email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                maxlength="255"
                                autocomplete="email"
                            >
                        </div>

                        <div>
                            <label>
                                <input
                                    type="checkbox"
                                    name="consent"
                                    value="1"
                                    required
                                >

                                موافقم برای پیگیری درخواست و اطلاع‌رسانی
                                مرتبط با این محصول با من تماس گرفته شود.
                                <a href="/privacy" target="_blank">
                                    سیاست حریم خصوصی
                                </a>
                            </label>
                        </div>

                        <button type="submit">
                            ثبت علاقه‌مندی
                        </button>
                    </form>

                @endif

            </div>
            @endif

        </section>

        @if ($content['show_preorder_form'])
        <section id="preorder-form" class="preorder-section">

            <h2>درخواست پیش‌خرید</h2>

            <p>
                اگر به این محصول علاقه‌مند هستید،
                اطلاعات خود را وارد کنید تا برای ادامه مراحل با شما تماس بگیریم.
            </p>

            @if (session('preorder_success'))
                <div class="success-message" role="status">
                    درخواست پیش‌خرید شما با موفقیت ثبت شد.
                </div>
            @endif

            @if ($errors->any())
                <div class="error-message" role="alert">
                    لطفاً اطلاعات واردشده را بررسی کنید.
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('preorders.store', ['slug' => $landingPage->slug]) }}"
            >
                @csrf

                <div class="form-group">
                    <label for="preorder-name">نام و نام خانوادگی</label>

                    <input
                        id="preorder-name"
                        type="text"
                        name="name"
                        maxlength="120"
                        value="{{ old('name') }}"
                        autocomplete="name"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="preorder-phone">شماره تماس</label>

                    <input
                        id="preorder-phone"
                        type="tel"
                        name="phone"
                        maxlength="30"
                        value="{{ old('phone') }}"
                        autocomplete="tel"
                        inputmode="tel"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>
                        <input
                            type="checkbox"
                            name="consent"
                            value="1"
                            required
                            @checked(old('consent'))
                        >

                        با ثبت اطلاعات تماس و پیگیری درخواست پیش‌خرید موافقم.
                    </label>
                </div>

                <button type="submit">
                    ثبت درخواست پیش‌خرید
                </button>
            </form>

        </section>
        @endif
    </main>

    <footer class="footer">
        <div class="container">
            ساخته‌شده با سیستم اعتبارسنجی ایده
        </div>
    </footer>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const cta = document.getElementById('main-cta');

        if (!cta) {
            return;
        }

        cta.addEventListener('click', function () {
            const url = @json(
                route('landing-pages.events.store', [
                    'slug' => $landingPage->slug
                ])
            );

            const token = document.querySelector(
                'meta[name="csrf-token"]'
            )?.getAttribute('content');

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
                body: JSON.stringify({
                    event_name: 'cta_click'
                }),
                keepalive: true
            }).catch(function () {
                // شکست رهگیری نباید مانع استفاده از صفحه شود.
            });
        });
    });
    </script>

</body>
</html>

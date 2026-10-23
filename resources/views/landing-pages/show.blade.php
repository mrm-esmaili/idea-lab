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
    </style>
</head>

<body>

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

                @if ($landingPage->cta_text)
                    <a
                        href="#lead-form"
                        class="cta"
                    >
                        {{ $landingPage->cta_text }}
                    </a>
                @endif

            </div>
        </section>

        <section class="content container">

            <h2>
                به‌زودی اطلاعات بیشتری منتشر می‌شود
            </h2>

            <p>
                اگر به این محصول علاقه‌مند هستید،
                می‌توانید برای دریافت اطلاعات بیشتر ثبت‌نام کنید.
            </p>

            <div id="lead-form">
                <p>
                    فرم ثبت‌نام در مرحله بعدی توسعه اضافه خواهد شد.
                </p>
            </div>

        </section>
    </main>

    <footer class="footer">
        <div class="container">
            ساخته‌شده با سیستم اعتبارسنجی ایده
        </div>
    </footer>

</body>
</html>
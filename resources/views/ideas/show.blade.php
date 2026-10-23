<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>{{ $idea->name }}</title>
</head>

<body>

    <a href="{{ route('ideas.index') }}">
        ← بازگشت به ایده‌ها
    </a>

    <h1>{{ $idea->name }}</h1>

    <p>
        {{ $idea->description }}
    </p>

    <p>
        وضعیت:
        {{ $idea->status }}
    </p>

    <hr>

    <a href="{{ route('landing-pages.create', $idea) }}">
        + ساخت Landing Page
    </a>

    <hr>

    <h2>Landing Pageها</h2>

    @forelse($idea->landingPages as $landingPage)

        <div>

            <h3>
                {{ $landingPage->name }}
            </h3>

            <a
                href="{{ route('landing-pages.show', $landingPage->slug) }}"
                target="_blank"
            >
                مشاهده Landing Page
            </a>

        </div>

        <hr>

    @empty

        <p>
            هنوز Landing Pageای برای این ایده ساخته نشده.
        </p>

    @endforelse

</body>
</html>

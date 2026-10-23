<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Landing Page جدید</title>
</head>

<body>

    <h1>
        ساخت Landing Page
    </h1>

    <p>
        ایده:
        {{ $idea->name }}
    </p>

    @if ($errors->any())

        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>

    @endif

    <form
        action="{{ route('landing-pages.store', $idea) }}"
        method="POST"
    >

        @csrf

        <div>
            <label>
                نام Landing Page
            </label>

            <input
                type="text"
                name="name"
                value="{{ old('name') }}"
                placeholder="مثلاً صفحه معرفی باشگاه‌یار"
                required
            >
        </div>

        <br>

        <div>
            <label>
                عنوان اصلی
            </label>

            <input
                type="text"
                name="title"
                value="{{ old('title') }}"
                placeholder="مثلاً باشگاهت را هوشمند مدیریت کن"
                required
            >
        </div>

        <br>

        <div>
            <label>
                توضیحات
            </label>

            <textarea
                name="description"
            >{{ old('description') }}</textarea>
        </div>

        <br>

        <div>
            <label>
                متن دکمه
            </label>

            <input
                type="text"
                name="cta_text"
                value="{{ old('cta_text') }}"
                placeholder="ثبت‌نام در لیست انتظار"
            >
        </div>

        <br>

        <button type="submit">
            ساخت Landing Page
        </button>

    </form>

</body>
</html>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ایده جدید</title>
</head>

<body>

    <h1>ایجاد ایده جدید</h1>

    @if ($errors->any())
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    @endif

    <form
        action="{{ route('ideas.store') }}"
        method="POST"
    >

        @csrf

        <div>
            <label>
                نام ایده
            </label>

            <input
                type="text"
                name="name"
                value="{{ old('name') }}"
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

        <button type="submit">
            ایجاد ایده
        </button>

    </form>

</body>
</html>

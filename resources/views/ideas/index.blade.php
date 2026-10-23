<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>ایده‌ها</title>
</head>

<body>

    <h1>ایده‌ها</h1>

    <a href="{{ route('ideas.create') }}">
        + ایجاد ایده جدید
    </a>

    <hr>

    @forelse($ideas as $idea)

        <div>
            <h3>
                <a href="{{ route('ideas.show', $idea) }}">
                    {{ $idea->name }}
                </a>
            </h3>

            <p>
                {{ $idea->description }}
            </p>

            <small>
                وضعیت: {{ $idea->status }}
            </small>
        </div>

        <hr>

    @empty

        <p>هنوز ایده‌ای ایجاد نشده است.</p>

    @endforelse

</body>
</html>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>مدیریت لیدها</title>
</head>
<body>

    <h1>مدیریت مشتریان بالقوه</h1>

    <p>
        <a href="{{ route('ideas.index') }}">مدیریت ایده‌ها</a>
    </p>

    <form method="GET" action="{{ route('admin.leads.index') }}">
        <label for="status">وضعیت</label>

        <select name="status" id="status">
            <option value="">همه وضعیت‌ها</option>

            @foreach ([
                'new' => 'جدید',
                'contacted' => 'تماس گرفته شد',
                'interested' => 'علاقه‌مند',
                'prepaid' => 'پیش‌پرداخت',
                'customer' => 'مشتری',
                'rejected' => 'رد شده',
            ] as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(request('status') === $value)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <label for="campaign">کمپین</label>

        <input
            id="campaign"
            type="text"
            name="campaign"
            value="{{ request('campaign') }}"
            placeholder="مثلاً clubyar01"
        >

        <button type="submit">فیلتر</button>

        <a href="{{ route('admin.leads.index') }}">پاک‌کردن فیلتر</a>
    </form>

    @if (session('success'))
        <p role="status">{{ session('success') }}</p>
    @endif

    <p>تعداد نتایج این صفحه: {{ $leads->count() }}</p>

    <table border="1" cellpadding="8" cellspacing="0">
        <thead>
            <tr>
                <th>نام</th>
                <th>شماره تماس</th>
                <th>ایده / صفحه</th>
                <th>منبع</th>
                <th>کمپین</th>
                <th>زمان ثبت</th>
                <th>وضعیت و یادداشت</th>
            </tr>
        </thead>

        <tbody>
            @forelse ($leads as $lead)
                <tr>
                    <td>{{ $lead->name }}</td>

                    <td>
                        <a href="tel:{{ $lead->phone }}">
                            {{ $lead->phone }}
                        </a>
                    </td>

                    <td>
                        {{ $lead->landingPage->idea->name ?? '—' }}
                        /
                        {{ $lead->landingPage->name ?? '—' }}
                    </td>

                    <td>{{ $lead->source ?? '—' }}</td>

                    <td>{{ $lead->campaign ?? '—' }}</td>

                    <td>
                        {{ $lead->created_at->format('Y-m-d H:i') }}
                    </td>

                    <td>
                        <form
                            method="POST"
                            action="{{ route('admin.leads.update', $lead) }}"
                        >
                            @csrf
                            @method('PATCH')

                            <select name="status" required>
                                @foreach ([
                                    'new' => 'جدید',
                                    'contacted' => 'تماس گرفته شد',
                                    'interested' => 'علاقه‌مند',
                                    'prepaid' => 'پیش‌پرداخت',
                                    'customer' => 'مشتری',
                                    'rejected' => 'رد شده',
                                ] as $value => $label)
                                    <option
                                        value="{{ $value }}"
                                        @selected($lead->status === $value)
                                    >
                                        {{ $label }}
                                    </option>
                                @endforeach
                            </select>

                            <textarea
                                name="notes"
                                rows="2"
                                maxlength="5000"
                                placeholder="یادداشت پیگیری"
                            >{{ $lead->notes }}</textarea>

                            <button type="submit">ذخیره</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">
                        هنوز لید ثبت نشده است.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{ $leads->links() }}

    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button type="submit">خروج از پنل</button>
    </form>

</body>
</html>

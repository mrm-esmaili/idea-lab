<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>مدیریت پیش‌خریدها</title>

    <style>
        body {
            margin: 0;
            padding: 24px;
            font-family: Tahoma, Arial, sans-serif;
            background: #f4f6fa;
            color: #202938;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .panel {
            background: white;
            padding: 20px;
            border-radius: 12px;
            overflow-x: auto;
        }

        .actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .button {
            display: inline-block;
            padding: 10px 14px;
            border: 0;
            border-radius: 8px;
            background: #26364d;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }

        table {
            width: 100%;
            min-width: 850px;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: right;
            font-size: 13px;
        }

        th {
            background: #fafbfc;
        }

        select {
            padding: 8px;
            border-radius: 6px;
            border: 1px solid #ddd;
        }

        .success {
            padding: 12px;
            margin-bottom: 16px;
            background: #e8f8ee;
            color: #176534;
            border-radius: 8px;
        }
    </style>
</head>

<body>
<div class="container">

    <h1>مدیریت درخواست‌های پیش‌خرید</h1>

    <div class="actions">
        <a class="button" href="{{ route('admin.dashboard') }}">
            داشبورد
        </a>

        @can('leads.manage')
        <a class="button" href="{{ route('admin.leads.index') }}">
            سرنخ‌ها
        </a>
        @endcan

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="button" type="submit">خروج</button>
        </form>
    </div>

    @if (session('success'))
        <div class="success">
            {{ session('success') }}
        </div>
    @endif

    <form method="GET" action="{{ route('admin.preorders.index') }}">
        <label for="status">فیلتر وضعیت:</label>

        <select id="status" name="status">
            <option value="">همه</option>

            @foreach ([
                'requested' => 'درخواست جدید',
                'contacted' => 'تماس گرفته شده',
                'interested' => 'علاقه‌مند',
                'rejected' => 'رد شده',
                'converted' => 'تبدیل‌شده',
            ] as $value => $label)
                <option
                    value="{{ $value }}"
                    @selected(request('status') === $value)
                >
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <button class="button" type="submit">فیلتر</button>
    </form>

    <br>

    <section class="panel">
        <table>
            <thead>
                <tr>
                    <th>نام</th>
                    <th>شماره تماس</th>
                    <th>ایده</th>
                    <th>صفحه فرود</th>
                    <th>کمپین</th>
                    <th>تاریخ ثبت</th>
                    <th>وضعیت</th>
                    <th>عملیات</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($preorders as $preorder)
                    <tr>
                        <td>{{ $preorder->name }}</td>

                        <td>
                            <a href="tel:{{ $preorder->phone }}">
                                {{ $preorder->phone }}
                            </a>
                        </td>

                        <td>
                            {{ $preorder->landingPage?->idea?->name ?? '—' }}
                        </td>

                        <td>
                            {{ $preorder->landingPage?->name ?? '—' }}
                        </td>

                        <td>
                            {{ $preorder->campaign ?? '—' }}
                        </td>

                        <td>
                            {{ $preorder->created_at?->format('Y-m-d H:i') }}
                        </td>

                        <td>{{ $preorder->status }}</td>

                        <td>
                            <form
                                method="POST"
                                action="{{ route('admin.preorders.update', $preorder) }}"
                            >
                                @csrf
                                @method('PATCH')

                                <select name="status">
                                    @foreach ([
                                        'requested' => 'درخواست جدید',
                                        'contacted' => 'تماس گرفته شده',
                                        'interested' => 'علاقه‌مند',
                                        'rejected' => 'رد شده',
                                        'converted' => 'تبدیل‌شده',
                                    ] as $value => $label)
                                        <option
                                            value="{{ $value }}"
                                            @selected($preorder->status === $value)
                                        >
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>

                                <button class="button" type="submit">
                                    ذخیره
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">
                            هنوز درخواست پیش‌خریدی ثبت نشده است.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 20px">
            {{ $preorders->links() }}
        </div>
    </section>

</div>
</body>
</html>

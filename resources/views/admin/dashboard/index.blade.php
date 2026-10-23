<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>داشبورد اعتبارسنجی ایده‌ها</title>

    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            background: #f4f6fa;
            color: #202938;
            font-family: Tahoma, Arial, sans-serif;
        }

        .container {
            max-width: 1200px;
            margin: auto;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 24px;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
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
            font-size: 13px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .card, .panel {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 3px 14px #1720330a;
        }

        .stat-label {
            color: #667085;
            font-size: 13px;
        }

        .stat-value {
            margin-top: 12px;
            font-size: 28px;
            font-weight: bold;
            direction: ltr;
            text-align: right;
        }

        .panel {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 13px 10px;
            text-align: right;
            border-bottom: 1px solid #edf0f4;
            font-size: 13px;
        }

        th {
            color: #667085;
            background: #fafbfc;
        }

        .muted {
            color: #667085;
            font-size: 12px;
        }

        @media (max-width: 800px) {
            body { padding: 14px; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 420px) {
            .stats { grid-template-columns: 1fr 1fr; gap: 8px; }
            .card { padding: 14px; }
            .stat-value { font-size: 22px; }
        }
    </style>
</head>

<body>
<div class="container">

    <header class="header">
        <div>
            <h1>داشبورد اعتبارسنجی</h1>
            <p class="muted">عملکرد ایده‌ها و صفحات فرود</p>
        </div>

        <div class="actions">
            <a class="button" href="{{ route('ideas.index') }}">
                مدیریت ایده‌ها
            </a>

            <a class="button" href="{{ route('admin.leads.index') }}">
                مدیریت سرنخ‌ها
            </a>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="button" type="submit">خروج</button>
            </form>
        </div>
    </header>

    <section class="stats">
        <div class="card">
            <div class="stat-label">کل بازدید صفحات</div>
            <div class="stat-value">
                {{ number_format($pageViews) }}
            </div>
        </div>

        <div class="card">
            <div class="stat-label">کل بازدیدکنندگان ثبت‌شده</div>
            <div class="stat-value">
                {{ number_format($visitors) }}
            </div>
        </div>

        <div class="card">
            <div class="stat-label">کل سرنخ‌ها</div>
            <div class="stat-value">
                {{ number_format($leads) }}
            </div>
        </div>

        <div class="card">
            <div class="stat-label">نرخ تبدیل تقریبی</div>
            <div class="stat-value">
                {{ number_format($conversionRate, 2) }}%
            </div>
        </div>
    </section>

    <section class="panel">
        <h2>عملکرد لندینگ‌پیج‌ها</h2>

        <table>
            <thead>
                <tr>
                    <th>ایده</th>
                    <th>صفحه فرود</th>
                    <th>وضعیت</th>
                    <th>بازدید</th>
                    <th>سرنخ</th>
                    <th>نرخ تبدیل</th>
                    <th>لینک</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($landingPages as $page)
                    @php
                        $rate = $page->page_views_count > 0
                            ? round(
                                ($page->leads_count / $page->page_views_count) * 100,
                                2
                            )
                            : 0;
                    @endphp

                    <tr>
                        <td>
                            {{ $page->idea?->name ?? 'بدون ایده' }}
                        </td>

                        <td>{{ $page->name }}</td>

                        <td>
                            {{ $page->is_active ? 'فعال' : 'غیرفعال' }}
                        </td>

                        <td>
                            {{ number_format($page->page_views_count) }}
                        </td>

                        <td>
                            {{ number_format($page->leads_count) }}
                        </td>

                        <td>{{ number_format($rate, 2) }}%</td>

                        <td>
                            <a
                                href="{{ route('landing-pages.show', ['slug' => $page->slug]) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                مشاهده صفحه
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">
                            هنوز لندینگ‌پیجی ثبت نشده است.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div style="margin-top: 20px">
            {{ $landingPages->links() }}
        </div>
    </section>

    <p class="muted" style="margin-top: 18px">
        نرخ تبدیل بر اساس تعداد سرنخ‌ها تقسیم بر تعداد بازدیدها محاسبه می‌شود.
        بازدید تکراری و سرنخ‌های نامعتبر ممکن است روی این عدد تأثیر بگذارند.
    </p>

</div>
</body>
</html>

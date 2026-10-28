<?php

namespace App\Services;

use App\Models\LandingPageEvent;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Preorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * پرس‌وجوی مشترک قیف تبدیل برای گزارش ایده و کمپین.
 *
 * تعاریف:
 * - page_views: تعداد رویداد page_view
 * - unique_visitors: تعداد بازدیدکننده یکتا (بر اساس کوکی) با رویداد page_view
 * - cta_clicks: تعداد رویداد cta_click
 * - leads / preorders: تعداد رکوردهای ثبت‌شده
 * - paid_count: تعداد سفارش‌های دارای پرداخت تأییدشده (پرداخت تکراری یک سفارش یک بار شمرده می‌شود)
 * - paid_rial: جمع وجه دریافت‌شده از پرداخت‌های تأییدشده (status = paid)
 * - lead_rate: سرنخ تقسیم بر بازدیدکننده یکتا (درصد)
 *
 * گروه‌بندی بر اساس صفحه فرود، یا صفحه فرود و کد utm_campaign (بدون حساسیت
 * به بزرگی حروف؛ کلید با حروف کوچک).
 */
class FunnelStats
{
    public const SUM_KEYS = [
        'page_views',
        'unique_visitors',
        'cta_clicks',
        'leads',
        'preorders',
        'paid_count',
        'paid_rial',
    ];

    /**
     * @return Collection<string, array<string, int|float|string|null>>
     *         کلید: «شناسه صفحه» یا «شناسه صفحه|کد کمپین» (کد خالی برای بدون کمپین)
     */
    public function grouped(Collection $pageIds, bool $byCampaign): Collection
    {
        if ($pageIds->isEmpty()) {
            return collect();
        }

        $group = $byCampaign ? ['landing_page_id', 'campaign'] : ['landing_page_id'];

        $events = $this->rows(
            LandingPageEvent::query()
                ->whereIn('landing_page_id', $pageIds)
                ->whereIn('event_name', ['page_view', 'cta_click'])
                ->selectRaw("SUM(CASE WHEN event_name = 'page_view' THEN 1 ELSE 0 END) AS page_views")
                ->selectRaw("COUNT(DISTINCT CASE WHEN event_name = 'page_view' THEN visitor_id END) AS unique_visitors")
                ->selectRaw("SUM(CASE WHEN event_name = 'cta_click' THEN 1 ELSE 0 END) AS cta_clicks"),
            $group
        );

        $leads = $this->rows(
            Lead::query()
                ->whereIn('landing_page_id', $pageIds)
                ->selectRaw('COUNT(*) AS leads'),
            $group
        );

        $preorders = $this->rows(
            Preorder::query()
                ->whereIn('landing_page_id', $pageIds)
                ->selectRaw('COUNT(*) AS preorders'),
            $group
        );

        $payments = $this->rows(
            Payment::query()
                ->join('preorders', 'preorders.id', '=', 'payments.preorder_id')
                ->whereIn('preorders.landing_page_id', $pageIds)
                ->where('payments.status', Payment::STATUS_PAID)
                ->selectRaw('COUNT(DISTINCT payments.preorder_id) AS paid_count')
                ->selectRaw('SUM(payments.amount_rial) AS paid_rial'),
            array_map(fn ($column) => 'preorders.' . $column, $group)
        );

        $keys = $byCampaign
            ? $events->keys()->merge($leads->keys())->merge($preorders->keys())->merge($payments->keys())->unique()
            : $pageIds->map(fn ($id) => (string) $id);

        return $keys->mapWithKeys(function ($key) use ($events, $leads, $preorders, $payments) {
            [$pageId, $code] = array_pad(explode('|', $key, 2), 2, null);

            $stats = [
                'landing_page_id' => (int) $pageId,
                'campaign' => $code === '' ? null : $code,
                'page_views' => (int) ($events[$key]->page_views ?? 0),
                'unique_visitors' => (int) ($events[$key]->unique_visitors ?? 0),
                'cta_clicks' => (int) ($events[$key]->cta_clicks ?? 0),
                'leads' => (int) ($leads[$key]->leads ?? 0),
                'preorders' => (int) ($preorders[$key]->preorders ?? 0),
                'paid_count' => (int) ($payments[$key]->paid_count ?? 0),
                'paid_rial' => (int) ($payments[$key]->paid_rial ?? 0),
            ];

            $stats['lead_rate'] = self::rate($stats['leads'], $stats['unique_visitors']);

            return [$key => $stats];
        });
    }

    /**
     * جمع شاخص‌ها؛ بازدیدکننده یکتا در سطح هر گروه شمرده می‌شود و جمع آن
     * ممکن است یک فرد را در چند گروه چند بار حساب کند.
     */
    public static function totals(Collection $stats): array
    {
        $totals = [];

        foreach (self::SUM_KEYS as $key) {
            $totals[$key] = (int) $stats->sum($key);
        }

        $totals['lead_rate'] = self::rate($totals['leads'], $totals['unique_visitors']);

        return $totals;
    }

    public static function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }

    public static function key(int $pageId, ?string $campaign = null, bool $byCampaign = true): string
    {
        return $byCampaign
            ? $pageId . '|' . mb_strtolower($campaign ?? '')
            : (string) $pageId;
    }

    /**
     * @param  list<string>  $group
     */
    private function rows(Builder $query, array $group): Collection
    {
        foreach ($group as $column) {
            $query->addSelect($column)->groupBy($column);
        }

        $merged = collect();

        foreach ($query->get() as $row) {
            $key = implode('|', array_map(
                fn ($column) => mb_strtolower((string) $row->{last(explode('.', $column))}),
                $group
            ));

            // کد کمپین بدون حساسیت به بزرگی حروف تطبیق می‌شود (مثل collation پیش‌فرض MariaDB)؛
            // گروه‌های هم‌کد در SQLite اینجا جمع می‌شوند
            if ($existing = $merged->get($key)) {
                foreach ($row->getAttributes() as $attribute => $value) {
                    if (is_numeric($value) && ! in_array($attribute, ['landing_page_id', 'campaign'], true)) {
                        $existing->{$attribute} = (int) $existing->{$attribute} + (int) $value;
                    }
                }

                continue;
            }

            $merged->put($key, $row);
        }

        return $merged;
    }
}

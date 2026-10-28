<?php

namespace App\Services;

use App\Models\Idea;
use App\Models\LandingPageEvent;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Preorder;
use Illuminate\Support\Collection;

/**
 * شاخص‌های عملکرد صفحات فرود یک ایده.
 *
 * تعاریف:
 * - page_views: تعداد رویداد page_view
 * - unique_visitors: تعداد بازدیدکننده یکتا (بر اساس کوکی) با رویداد page_view
 * - leads / preorders: تعداد رکوردهای ثبت‌شده
 * - paid_count: تعداد سفارش‌های دارای پرداخت تأییدشده (پرداخت تکراری یک سفارش یک بار شمرده می‌شود)
 * - paid_rial: جمع وجه دریافت‌شده از پرداخت‌های تأییدشده (status = paid)
 * - lead_rate: سرنخ تقسیم بر بازدیدکننده یکتا
 */
class IdeaStatsService
{
    /**
     * @return Collection<int, array<string, int|float>> کلید: شناسه صفحه فرود
     */
    public function forLandingPages(Idea $idea): Collection
    {
        $pageIds = $idea->landingPages()->pluck('id');

        if ($pageIds->isEmpty()) {
            return collect();
        }

        $events = LandingPageEvent::query()
            ->whereIn('landing_page_id', $pageIds)
            ->whereIn('event_name', ['page_view', 'cta_click'])
            ->selectRaw('landing_page_id')
            ->selectRaw("SUM(CASE WHEN event_name = 'page_view' THEN 1 ELSE 0 END) AS page_views")
            ->selectRaw("COUNT(DISTINCT CASE WHEN event_name = 'page_view' THEN visitor_id END) AS unique_visitors")
            ->selectRaw("SUM(CASE WHEN event_name = 'cta_click' THEN 1 ELSE 0 END) AS cta_clicks")
            ->groupBy('landing_page_id')
            ->get()
            ->keyBy('landing_page_id');

        $leads = Lead::query()
            ->whereIn('landing_page_id', $pageIds)
            ->selectRaw('landing_page_id, COUNT(*) AS total')
            ->groupBy('landing_page_id')
            ->pluck('total', 'landing_page_id');

        $preorders = Preorder::query()
            ->whereIn('landing_page_id', $pageIds)
            ->selectRaw('landing_page_id, COUNT(*) AS total')
            ->groupBy('landing_page_id')
            ->pluck('total', 'landing_page_id');

        $payments = Payment::query()
            ->join('preorders', 'preorders.id', '=', 'payments.preorder_id')
            ->whereIn('preorders.landing_page_id', $pageIds)
            ->where('payments.status', 'paid')
            ->selectRaw('preorders.landing_page_id AS landing_page_id')
            ->selectRaw('COUNT(DISTINCT payments.preorder_id) AS paid_count')
            ->selectRaw('SUM(payments.amount_rial) AS paid_rial')
            ->groupBy('preorders.landing_page_id')
            ->get()
            ->keyBy('landing_page_id');

        return $pageIds->mapWithKeys(function ($id) use ($events, $leads, $preorders, $payments) {
            $uniqueVisitors = (int) ($events[$id]->unique_visitors ?? 0);
            $leadCount = (int) ($leads[$id] ?? 0);

            return [$id => [
                'page_views' => (int) ($events[$id]->page_views ?? 0),
                'unique_visitors' => $uniqueVisitors,
                'cta_clicks' => (int) ($events[$id]->cta_clicks ?? 0),
                'leads' => $leadCount,
                'preorders' => (int) ($preorders[$id] ?? 0),
                'paid_count' => (int) ($payments[$id]->paid_count ?? 0),
                'paid_rial' => (int) ($payments[$id]->paid_rial ?? 0),
                'lead_rate' => $this->rate($leadCount, $uniqueVisitors),
            ]];
        });
    }

    /**
     * جمع شاخص‌ها؛ بازدیدکننده یکتا در سطح هر صفحه شمرده می‌شود و
     * جمع آن ممکن است یک فرد را در چند صفحه چند بار حساب کند.
     */
    public function totals(Collection $pageStats): array
    {
        $keys = [
            'page_views',
            'unique_visitors',
            'cta_clicks',
            'leads',
            'preorders',
            'paid_count',
            'paid_rial',
        ];

        $totals = [];

        foreach ($keys as $key) {
            $totals[$key] = (int) $pageStats->sum($key);
        }

        $totals['lead_rate'] = $this->rate(
            $totals['leads'],
            $totals['unique_visitors']
        );

        return $totals;
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 2) : 0.0;
    }
}

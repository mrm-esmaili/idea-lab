<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\Expense;
use App\Models\Experiment;
use App\Models\Idea;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * شاخص‌های یکسان آزمایش و مقایسه کمپین (مراحل ۶۸ و ۶۹).
 *
 * داده معتبر: فقط رویدادها، سرنخ‌ها، پیش‌خریدها و پرداخت‌های تأییدشده‌ای که
 * در بازه تاریخ (به وقت نمایش) روی صفحات ایده و، اگر کمپین انتخاب شده،
 * با کد همان کمپین‌ها ثبت شده‌اند (قاعده انتساب App\Models\Campaign). هزینه
 * فقط از هزینه‌های ثبت‌شده با تاریخ وقوع در بازه؛ بودجه برنامه‌ریزی‌شده
 * هزینه حساب نمی‌شود.
 */
class ExperimentMetrics
{
    /** کلید => [برچسب، واحد (count|percent|rial)، تعریف] */
    public const METRICS = [
        'spent_rial' => ['هزینه', 'rial', 'جمع هزینه‌های ثبت‌شده در بازه (برای کمپین: هزینه‌های همان کمپین؛ برای کل ایده: همه هزینه‌های ایده)'],
        'unique_visitors' => ['بازدیدکننده یکتا', 'count', 'بازدیدکننده یکتا (کوکی) با دست‌کم یک بازدید صفحه در بازه'],
        'leads' => ['سرنخ', 'count', 'فرم‌های علاقه‌مندی ثبت‌شده در بازه'],
        'lead_rate' => ['نرخ سرنخ', 'percent', 'سرنخ ÷ بازدیدکننده یکتا'],
        'preorders' => ['پیش‌خرید', 'count', 'درخواست‌های پیش‌خرید ثبت‌شده در بازه'],
        'paid_count' => ['پیش‌پرداخت', 'count', 'سفارش‌هایی که پرداختشان در بازه توسط درگاه تأیید شده'],
        'paid_rial' => ['مبلغ پیش‌پرداخت', 'rial', 'جمع پرداخت‌های تأییدشده در بازه (پیش از کسر بازپرداخت)'],
        'paid_rate' => ['نرخ تبدیل به پرداخت', 'percent', 'پیش‌پرداخت ÷ بازدیدکننده یکتا'],
        'lead_to_paid_rate' => ['تبدیل سرنخ به پرداخت', 'percent', 'پیش‌پرداخت ÷ سرنخ'],
        'cost_per_lead_rial' => ['هزینه هر سرنخ', 'rial', 'هزینه ÷ سرنخ'],
        'cost_per_paid_rial' => ['هزینه جذب هر مشتری', 'rial', 'هزینه ÷ پیش‌پرداخت (CAC)'],
    ];

    public function __construct(
        private readonly FunnelStats $funnel,
    ) {}

    /**
     * بازه تاریخ به وقت نمایش ← بازه UTC؛ انتهای بازه آینده به «اکنون» محدود می‌شود.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function bounds(Carbon|string $from, Carbon|string $to): array
    {
        $tz = config('app.display_timezone');

        $start = Carbon::parse($from instanceof Carbon ? $from->toDateString() : $from, $tz)->startOfDay()->utc();
        $end = Carbon::parse($to instanceof Carbon ? $to->toDateString() : $to, $tz)->endOfDay()->utc();

        return [$start, $end->min(now())];
    }

    /**
     * ردیف هر کمپین با تعاریف یکسان.
     *
     * @param  Collection<int, Campaign>  $campaigns
     * @return Collection<int, array> کلید: شناسه کمپین
     */
    public function campaignRows(Collection $campaigns, Carbon $from, Carbon $to): Collection
    {
        if ($campaigns->isEmpty()) {
            return collect();
        }

        $grouped = $this->funnel->grouped($campaigns->pluck('landing_page_id')->unique()->values(), true, $from, $to);
        $spent = $this->spent(fn ($q) => $q->whereIn('campaign_id', $campaigns->pluck('id')), $from, $to, 'campaign_id');

        return $campaigns->mapWithKeys(function (Campaign $campaign) use ($grouped, $spent) {
            $stats = $grouped->get(FunnelStats::key($campaign->landing_page_id, $campaign->slug)) ?? [];

            return [$campaign->id => $this->derive($stats, (int) ($spent[$campaign->id] ?? 0))];
        });
    }

    /**
     * کل ترافیک صفحات ایده (همه کمپین‌ها و بدون کمپین).
     */
    public function ideaTotals(Idea $idea, Carbon $from, Carbon $to): array
    {
        $stats = FunnelStats::totals($this->funnel->grouped($idea->landingPages()->pluck('id'), false, $from, $to));
        $spent = $this->spent(fn ($q) => $q->where('idea_id', $idea->id), $from, $to);

        return $this->derive($stats, (int) $spent->sum());
    }

    /**
     * نتیجه آزمایش در لحظه فعلی: بازه، جمع، تفکیک کمپین و ارزیابی معیارها.
     */
    public function forExperiment(Experiment $experiment): array
    {
        [$from, $to] = $this->bounds($experiment->starts_on, $experiment->ends_on);
        $started = $from->lte(now());

        $campaigns = $experiment->campaigns()->with('landingPage')->get();
        $rows = $started ? $this->campaignRows($campaigns, $from, $to) : collect();

        $totals = ! $started
            ? $this->derive([], 0)
            : ($campaigns->isEmpty()
                ? $this->ideaTotals($experiment->idea, $from, $to)
                : $this->sumRows($rows));

        return [
            'from' => $from->toIso8601String(),
            'to' => $to->toIso8601String(),
            'scope' => $campaigns->isEmpty() ? 'idea' : 'campaigns',
            'campaign_ids' => $campaigns->pluck('id')->all(),
            'totals' => $totals,
            'campaigns' => $rows->all(),
            'evaluation' => $this->evaluate($experiment, $totals),
            'computed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * عملکرد دامنه آزمایش در بازه دلخواه (مثلاً از تاریخ تصمیم تا امروز برای ارزیابی پس از تصمیم).
     */
    public function scopeTotals(Experiment $experiment, Carbon $from, Carbon $to): array
    {
        $campaigns = $experiment->campaigns()->with('landingPage')->get();

        return $campaigns->isEmpty()
            ? $this->ideaTotals($experiment->idea, $from, $to)
            : $this->sumRows($this->campaignRows($campaigns, $from, $to));
    }

    /**
     * ارزیابی معیارها؛ پیشنهاد تصمیم نیست و تصمیم نهایی با مدیر است.
     */
    public function evaluate(Experiment $experiment, array $totals): array
    {
        $budget = $experiment->budget_rial;

        $criteria = collect($experiment->criteria ?? [])->map(function (array $criterion) use ($totals) {
            $value = $totals[$criterion['metric']] ?? null;
            $met = $value === null ? null : ($criterion['operator'] === 'gte'
                ? $value >= $criterion['target']
                : $value <= $criterion['target']);

            return $criterion + ['value' => $value, 'met' => $met];
        });

        $success = $criteria->where('kind', 'success');
        $stops = $criteria->where('kind', 'stop');

        return [
            'criteria' => $criteria->values()->all(),
            'sample_ok' => $totals['unique_visitors'] >= $experiment->min_sample,
            'budget_exceeded' => $budget !== null && $totals['spent_rial'] > $budget,
            'success_met' => $success->isNotEmpty() && $success->every(fn ($c) => $c['met'] === true),
            'stop_triggered' => $stops->contains(fn ($c) => $c['met'] === true),
        ];
    }

    private function derive(array $stats, int $spent): array
    {
        $row = ['spent_rial' => $spent];

        foreach (FunnelStats::SUM_KEYS as $key) {
            $row[$key] = (int) ($stats[$key] ?? 0);
        }

        $row['lead_rate'] = FunnelStats::rate($row['leads'], $row['unique_visitors']);
        $row['paid_rate'] = FunnelStats::rate($row['paid_count'], $row['unique_visitors']);
        $row['lead_to_paid_rate'] = FunnelStats::rate($row['paid_count'], $row['leads']);
        $row['cost_per_lead_rial'] = $spent > 0 && $row['leads'] > 0 ? intdiv($spent, $row['leads']) : null;
        $row['cost_per_paid_rial'] = $spent > 0 && $row['paid_count'] > 0 ? intdiv($spent, $row['paid_count']) : null;

        return $row;
    }

    private function sumRows(Collection $rows): array
    {
        $stats = [];

        foreach (FunnelStats::SUM_KEYS as $key) {
            $stats[$key] = (int) $rows->sum($key);
        }

        return $this->derive($stats, (int) $rows->sum('spent_rial'));
    }

    private function spent(callable $scope, Carbon $from, Carbon $to, ?string $groupBy = null): Collection
    {
        $tz = config('app.display_timezone');

        $query = Expense::query()
            ->whereDate('incurred_on', '>=', $from->copy()->timezone($tz)->toDateString())
            ->whereDate('incurred_on', '<=', $to->copy()->timezone($tz)->toDateString());

        $scope($query);

        if (! $groupBy) {
            return collect([(int) $query->sum('amount_rial')]);
        }

        return $query->groupBy($groupBy)->selectRaw("{$groupBy}, SUM(amount_rial) AS total")->pluck('total', $groupBy);
    }
}

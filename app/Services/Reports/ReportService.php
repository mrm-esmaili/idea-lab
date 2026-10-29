<?php

namespace App\Services\Reports;

use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Expense;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\SmsBroadcast;
use App\Models\SmsMessage;
use App\Services\FinanceReportService;
use App\Services\FunnelStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * گزارش‌های مراحل ۷۴ تا ۷۸. همه بازه‌ها روز به وقت نمایش‌اند و شاخص‌های
 * قیف از FunnelStats می‌آیند تا تعریف‌ها در همه گزارش‌ها یکی باشد.
 */
class ReportService
{
    /** حداقل مشتری پرداخت‌کننده برای نمایش هزینه جذب (CAC) */
    public const CAC_MIN_CUSTOMERS = 3;

    public function __construct(
        private readonly FunnelStats $funnel,
        private readonly FinanceReportService $finance,
    ) {}

    /**
     * @return array{0: Carbon, 1: Carbon} بازه UTC
     */
    public function bounds(string $from, string $to): array
    {
        $tz = config('app.display_timezone');

        return [
            Carbon::parse($from, $tz)->startOfDay()->utc(),
            Carbon::parse($to, $tz)->endOfDay()->utc(),
        ];
    }

    /**
     * قیف تبدیل (مرحله ۷۴): مراحل با نرخ نسبت به بازدیدکننده یکتا و نسبت به مرحله قبل.
     */
    public function funnel(?int $ideaId, Carbon $from, Carbon $to): array
    {
        $pages = LandingPage::when($ideaId, fn ($q) => $q->where('idea_id', $ideaId))->get(['id', 'idea_id']);
        $byPage = $this->funnel->grouped($pages->pluck('id'), false, $from, $to);
        $totals = FunnelStats::totals($byPage);

        $steps = [];
        $previous = null;

        foreach ([
            'unique_visitors' => ['بازدیدکننده یکتا', 'بازدیدکننده (کوکی) با دست‌کم یک بازدید صفحه'],
            'leads' => ['سرنخ', 'فرم علاقه‌مندی ثبت‌شده'],
            'preorders' => ['درخواست پیش‌خرید', 'فرم پیش‌خرید ثبت‌شده'],
            'paid_count' => ['پیش‌پرداخت موفق', 'سفارشی که پرداختش توسط درگاه تأیید شده'],
        ] as $key => [$label, $definition]) {
            $value = $totals[$key];
            $steps[] = [
                'key' => $key,
                'label' => $label,
                'definition' => $definition,
                'value' => $value,
                'of_visitors' => FunnelStats::rate($value, $totals['unique_visitors']),
                'of_previous' => $previous === null ? null : FunnelStats::rate($value, $previous),
            ];
            $previous = $value;
        }

        $ideas = Idea::whereIn('id', $pages->pluck('idea_id')->unique())->orderBy('name')->get(['id', 'name']);
        $perIdea = $ideas->map(function (Idea $idea) use ($pages, $byPage) {
            $stats = FunnelStats::totals($byPage->only($pages->where('idea_id', $idea->id)->pluck('id')->map(fn ($id) => (string) $id)->all()));

            return ['idea' => $idea] + $stats + ['paid_rate' => FunnelStats::rate($stats['paid_count'], $stats['unique_visitors'])];
        });

        return [
            'steps' => $steps,
            'totals' => $totals,
            'page_views' => $totals['page_views'],
            // کلیک دکمه پیش‌نیاز ثبت فرم نیست و جزو مراحل قیف شمرده نمی‌شود
            'cta_clicks' => $totals['cta_clicks'],
            'per_idea' => $perIdea,
        ];
    }

    /**
     * اقتصاد ایده (مرحله ۷۵): ارقام مالی از گزارش مالی (مبنای نقدی) و شمارش‌ها از قیف.
     * CAC = هزینه تبلیغات ÷ مشتری پرداخت‌کننده، فقط با دست‌کم CAC_MIN_CUSTOMERS مشتری.
     */
    public function ideaEconomics(Carbon $from, Carbon $to): Collection
    {
        $tz = config('app.display_timezone');
        $finance = collect($this->finance->build($from->copy()->timezone($tz), $to->copy()->timezone($tz), null)['rows'])
            ->keyBy(fn ($row) => $row['idea']?->id ?? 0);

        $pages = LandingPage::get(['id', 'idea_id']);
        $byPage = $this->funnel->grouped($pages->pluck('id'), false, $from, $to);

        $ads = Expense::where('category', 'ads')
            ->whereDate('incurred_on', '>=', $from->copy()->timezone($tz)->toDateString())
            ->whereDate('incurred_on', '<=', $to->copy()->timezone($tz)->toDateString())
            ->groupBy('idea_id')
            ->selectRaw('idea_id, SUM(amount_rial) AS total')
            ->pluck('total', 'idea_id');

        return Idea::orderBy('name')->get()->map(function (Idea $idea) use ($finance, $pages, $byPage, $ads) {
            $money = $finance->get($idea->id) ?? [];
            $stats = FunnelStats::totals($byPage->only($pages->where('idea_id', $idea->id)->pluck('id')->map(fn ($id) => (string) $id)->all()));
            $adSpend = (int) ($ads[$idea->id] ?? 0);

            return [
                'idea' => $idea,
                'expenses' => (int) ($money['expenses'] ?? 0),
                'ad_spend' => $adSpend,
                'received' => (int) ($money['received'] ?? 0),
                'refunded' => (int) ($money['refunded'] ?? 0),
                'net' => (int) ($money['net'] ?? 0),
                'recognized' => (int) ($money['recognized'] ?? 0),
                'deferred' => (int) ($money['deferred'] ?? 0),
                'leads' => $stats['leads'],
                'customers' => $stats['paid_count'],
                'cost_per_lead' => $adSpend > 0 && $stats['leads'] > 0 ? intdiv($adSpend, $stats['leads']) : null,
                'cac' => $adSpend > 0 && $stats['paid_count'] >= self::CAC_MIN_CUSTOMERS ? intdiv($adSpend, $stats['paid_count']) : null,
                'cac_insufficient' => $stats['paid_count'] < self::CAC_MIN_CUSTOMERS,
            ];
        })->filter(fn ($row) => $row['expenses'] || $row['received'] || $row['leads'] || $row['customers'])->values();
    }

    /**
     * عملکرد منابع جذب (مرحله ۷۶) بر اساس utm_source ثبت‌شده روی هر اقدام؛
     * هزینه از هزینه‌های کمپین‌هایی که همین منبع را دارند.
     */
    public function sources(Carbon $from, Carbon $to): Collection
    {
        $tz = config('app.display_timezone');
        $key = fn ($value) => mb_strtolower(trim((string) $value));

        $sum = function (Builder $query, string $column, string $select) use ($key) {
            return $query->groupBy($column)->selectRaw("{$column} AS src, {$select} AS total")->get()
                ->groupBy(fn ($row) => $key($row->src))
                ->map(fn ($rows) => (int) $rows->sum('total'));
        };

        $visitors = LandingPageEvent::where('event_name', 'page_view')->whereBetween('occurred_at', [$from, $to])
            ->select('source', 'visitor_id')->distinct()->get()
            ->groupBy(fn ($row) => $key($row->source))
            ->map(fn ($rows) => $rows->unique('visitor_id')->count());
        $leads = $sum(Lead::whereBetween('created_at', [$from, $to]), 'source', 'COUNT(*)');
        $preorders = $sum(Preorder::whereBetween('created_at', [$from, $to]), 'source', 'COUNT(*)');
        $paid = $sum(Payment::join('preorders', 'preorders.id', '=', 'payments.preorder_id')->where('payments.status', Payment::STATUS_PAID)->whereBetween('payments.verified_at', [$from, $to]), 'preorders.source', 'COUNT(DISTINCT payments.preorder_id)');
        $paidRial = $sum(Payment::join('preorders', 'preorders.id', '=', 'payments.preorder_id')->where('payments.status', Payment::STATUS_PAID)->whereBetween('payments.verified_at', [$from, $to]), 'preorders.source', 'SUM(payments.amount_rial)');
        $spent = $sum(Expense::join('campaigns', 'campaigns.id', '=', 'expenses.campaign_id')
            ->whereDate('expenses.incurred_on', '>=', $from->copy()->timezone($tz)->toDateString())
            ->whereDate('expenses.incurred_on', '<=', $to->copy()->timezone($tz)->toDateString()), 'campaigns.source', 'SUM(expenses.amount_rial)');

        return collect([$visitors, $leads, $preorders, $paid, $spent])
            ->flatMap(fn ($c) => $c->keys())->unique()
            ->map(fn ($source) => [
                'source' => $source === '' ? null : $source,
                'unique_visitors' => (int) ($visitors[$source] ?? 0),
                'leads' => (int) ($leads[$source] ?? 0),
                'preorders' => (int) ($preorders[$source] ?? 0),
                'paid_count' => (int) ($paid[$source] ?? 0),
                'paid_rial' => (int) ($paidRial[$source] ?? 0),
                'spent_rial' => (int) ($spent[$source] ?? 0),
            ])
            ->map(fn ($row) => $row + [
                'lead_rate' => FunnelStats::rate($row['leads'], $row['unique_visitors']),
                'paid_rate' => FunnelStats::rate($row['paid_count'], $row['unique_visitors']),
                'cost_per_lead' => $row['spent_rial'] > 0 && $row['leads'] > 0 ? intdiv($row['spent_rial'], $row['leads']) : null,
            ])
            ->sortByDesc('unique_visitors')
            ->values();
    }

    /**
     * باشگاه مشتریان (مرحله ۷۷) با تفکیک رضایت پیامکی.
     */
    public function contacts(Carbon $from, Carbon $to): array
    {
        $consentSplit = fn (Builder $query) => [
            'total' => (clone $query)->count(),
            'consent' => (clone $query)->where('contacts.marketing_sms_consent', true)->count(),
            'opted_out' => (clone $query)->where('contacts.marketing_sms_consent', false)->whereNotNull('contacts.marketing_opted_out_at')->count(),
        ];

        $bySource = Contact::groupBy('first_source')
            ->selectRaw('first_source, COUNT(*) AS total, SUM(CASE WHEN marketing_sms_consent = 1 THEN 1 ELSE 0 END) AS consent')
            ->get()
            ->groupBy(fn ($row) => mb_strtolower(trim((string) $row->first_source)))
            ->map(fn ($rows, $source) => [
                'source' => $source === '' ? null : $source,
                'total' => (int) $rows->sum('total'),
                'consent' => (int) $rows->sum('consent'),
            ])
            ->sortByDesc('total')
            ->values();

        $groups = ContactGroup::orderBy('name')->get()->map(fn (ContactGroup $group) => [
            'group' => $group,
        ] + $consentSplit($group->membersQuery()));

        return [
            'all' => $consentSplit(Contact::query()),
            'new' => $consentSplit(Contact::whereBetween('contacts.created_at', [$from, $to])),
            'consented_in_range' => Contact::whereBetween('marketing_consent_at', [$from, $to])->count(),
            'opted_out_in_range' => Contact::whereBetween('marketing_opted_out_at', [$from, $to])->count(),
            'by_source' => $bySource,
            'groups' => $groups,
        ];
    }

    /**
     * پیامک و هزینه (مرحله ۷۸)؛ «ارسال شد» یعنی پذیرش توسط ارائه‌دهنده، نه تحویل.
     */
    public function sms(Carbon $from, Carbon $to): array
    {
        $base = fn () => SmsMessage::whereBetween('created_at', [$from, $to]);

        $byCategory = $base()->groupBy('category', 'status')
            ->selectRaw('category, status, COUNT(*) AS total, SUM(cost) AS cost')
            ->get()
            ->groupBy('category')
            ->map(fn ($rows) => [
                'statuses' => $rows->mapWithKeys(fn ($r) => [$r->status => (int) $r->total])->all(),
                'total' => (int) $rows->sum('total'),
                'cost' => (float) $rows->sum('cost'),
            ]);

        $byTemplate = $base()->whereNotNull('template_key')->groupBy('template_key')
            ->selectRaw("template_key, COUNT(*) AS total, SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent, SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed, SUM(cost) AS cost")
            ->orderByDesc('total')
            ->get();

        $errors = $base()->where('status', SmsMessage::STATUS_FAILED)->whereNotNull('error')
            ->groupBy('error')
            ->selectRaw('error, COUNT(*) AS total')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $all = $byCategory->sum('total');
        $sent = $byCategory->sum(fn ($c) => $c['statuses'][SmsMessage::STATUS_SENT] ?? 0);
        $failed = $byCategory->sum(fn ($c) => $c['statuses'][SmsMessage::STATUS_FAILED] ?? 0);

        return [
            'total' => $all,
            'sent' => $sent,
            'failed' => $failed,
            'failure_rate' => FunnelStats::rate($failed, $sent + $failed),
            'cost' => (float) $byCategory->sum('cost'),
            'by_category' => $byCategory,
            'by_template' => $byTemplate,
            'errors' => $errors,
            'broadcasts' => SmsBroadcast::whereBetween('confirmed_at', [$from, $to])->latest('confirmed_at')->get(),
        ];
    }
}

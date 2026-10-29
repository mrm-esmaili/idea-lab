<?php

namespace App\Services\Reports;

use App\Models\Experiment;
use App\Models\ExperimentDecision;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\RefundRequest;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\FunnelStats;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * داشبورد کلی (مرحله ۷۳): کارهای نیازمند رسیدگی بر اساس مجوز کاربر،
 * فعالیت ۷ روز اخیر در برابر ۷ روز قبل از آن، و وضعیت ایده‌ها.
 */
class DashboardService
{
    public function __construct(
        private readonly FunnelStats $funnel,
    ) {}

    /**
     * @return list<array{label: string, count: int, url: string, color: string, icon: string}>
     */
    public function attention(User $user): array
    {
        $today = now()->timezone(config('app.display_timezone'))->toDateString();

        $items = [
            ['finance.manage', 'درخواست بازپرداخت در انتظار بررسی', 'bi-arrow-counterclockwise', 'warning',
                fn () => RefundRequest::where('status', RefundRequest::STATUS_PENDING)->count(),
                fn () => route('admin.refunds.index', ['status' => RefundRequest::STATUS_PENDING])],
            ['finance.manage', 'بازپرداخت تأییدشده‌ای که هنوز واریز نشده', 'bi-cash-coin', 'danger',
                fn () => RefundRequest::where('status', RefundRequest::STATUS_APPROVED)->count(),
                fn () => route('admin.refunds.index', ['status' => RefundRequest::STATUS_APPROVED])],
            ['finance.manage', 'سفارش با پرداخت تکراری (نیازمند بازپرداخت)', 'bi-files', 'danger',
                fn () => DB::table('payments')->where('status', Payment::STATUS_PAID)->groupBy('preorder_id')->havingRaw('COUNT(*) > 1')->pluck('preorder_id')->count(),
                fn () => route('admin.payments.index', ['duplicates' => 1])],
            ['finance.manage', 'پرداخت معلق بیش از ۳۰ دقیقه (نیازمند استعلام)', 'bi-hourglass-split', 'warning',
                fn () => Payment::where('status', Payment::STATUS_PENDING)->whereNotNull('authority')->where('created_at', '<', now()->subMinutes(30))->count(),
                fn () => route('admin.payments.index', ['status' => Payment::STATUS_PENDING])],
            ['leads.manage', 'سرنخ جدید پیگیری‌نشده بیش از ۲ روز', 'bi-person-exclamation', 'info',
                fn () => Lead::where('status', 'new')->where('created_at', '<', now()->subDays(2))->count(),
                fn () => route('admin.leads.index', ['status' => 'new'])],
            ['experiments.decide', 'بازبینی سررسیده تصمیم آزمایش', 'bi-alarm', 'warning',
                fn () => ExperimentDecision::whereNull('reviewed_at')->whereNotNull('review_on')->whereDate('review_on', '<=', $today)->count(),
                fn () => route('admin.experiments.index')],
            ['ideas.manage', 'آزمایش پایان‌یافته بدون تصمیم', 'bi-clipboard-check', 'info',
                fn () => Experiment::whereNull('cancelled_at')->whereDate('ends_on', '<', $today)->whereDoesntHave('decisions')->count(),
                fn () => route('admin.experiments.index', ['status' => 'ended'])],
            ['sms.send', 'پیامک ناموفق در ۲۴ ساعت اخیر', 'bi-chat-left-dots', 'danger',
                fn () => SmsMessage::where('status', SmsMessage::STATUS_FAILED)->where('created_at', '>=', now()->subDay())->count(),
                fn () => route('admin.sms.messages.index', ['status' => SmsMessage::STATUS_FAILED])],
            ['settings.manage', 'پیامک مانده در صف بیش از ۱۰ دقیقه (Cron بررسی شود)', 'bi-clock-history', 'warning',
                fn () => SmsMessage::where('status', SmsMessage::STATUS_QUEUED)->where('available_at', '<', now()->subMinutes(10))->count(),
                fn () => route('admin.sms.index')],
        ];

        $result = [];

        foreach ($items as [$permission, $label, $icon, $color, $count, $url]) {
            if (! $user->hasPermission($permission)) {
                continue;
            }

            $value = $count();

            if ($value > 0) {
                $result[] = ['label' => $label, 'count' => $value, 'url' => $url(), 'color' => $color, 'icon' => $icon];
            }
        }

        return $result;
    }

    /**
     * قیف ۷ روز اخیر و ۷ روز قبل (روز به وقت نمایش).
     */
    public function activity(): array
    {
        $tz = config('app.display_timezone');
        $pageIds = LandingPage::pluck('id');

        $period = function (int $daysAgoStart, int $daysAgoEnd) use ($tz, $pageIds) {
            $from = now($tz)->subDays($daysAgoStart)->startOfDay()->utc();
            $to = now($tz)->subDays($daysAgoEnd)->endOfDay()->utc();

            return FunnelStats::totals($this->funnel->grouped($pageIds, false, $from, $to));
        };

        return ['current' => $period(6, 0), 'previous' => $period(13, 7)];
    }

    /**
     * روند روزانه N روز اخیر (روز به وقت نمایش): بازدیدکننده یکتا، سرنخ، پیش‌پرداخت.
     *
     * @return list<array{date: string, unique_visitors: int, leads: int, paid_count: int}>
     */
    public function daily(int $days = 30): array
    {
        $tz = config('app.display_timezone');
        $from = now($tz)->subDays($days - 1)->startOfDay();
        $fromUtc = $from->copy()->utc();
        $day = fn ($value) => Carbon::parse($value, 'UTC')->timezone($tz)->toDateString();

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $series[$from->copy()->addDays($i)->toDateString()] = ['unique_visitors' => [], 'leads' => 0, 'paid_count' => 0];
        }

        LandingPageEvent::where('event_name', 'page_view')->where('occurred_at', '>=', $fromUtc)
            ->toBase()->select('occurred_at', 'visitor_id')->orderBy('id')
            ->chunk(5000, function ($rows) use (&$series, $day) {
                foreach ($rows as $row) {
                    $date = $day($row->occurred_at);
                    if (isset($series[$date])) {
                        $series[$date]['unique_visitors'][$row->visitor_id] = true;
                    }
                }
            });

        foreach (Lead::where('created_at', '>=', $fromUtc)->toBase()->pluck('created_at') as $at) {
            if (isset($series[$date = $day($at)])) {
                $series[$date]['leads']++;
            }
        }

        foreach (Payment::where('status', Payment::STATUS_PAID)->where('verified_at', '>=', $fromUtc)->toBase()->select('verified_at', 'preorder_id')->get()->unique('preorder_id') as $row) {
            if (isset($series[$date = $day($row->verified_at)])) {
                $series[$date]['paid_count']++;
            }
        }

        return collect($series)->map(fn ($v, $date) => [
            'date' => $date,
            'unique_visitors' => count($v['unique_visitors']),
            'leads' => $v['leads'],
            'paid_count' => $v['paid_count'],
        ])->values()->all();
    }

    /**
     * @return array<string, int>
     */
    public function ideasByStatus(): array
    {
        return Idea::whereNull('archived_at')
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) AS total')
            ->pluck('total', 'status')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public static function change(int|float $current, int|float $previous): ?float
    {
        return $previous > 0 ? round(($current - $previous) / $previous * 100, 1) : null;
    }
}

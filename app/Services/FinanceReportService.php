<?php

namespace App\Services;

use App\Enums\IdeaStatus;
use App\Models\Expense;
use App\Models\Idea;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RefundRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * گزارش مالی عملیاتی (مرحله ۵۷) — مبنای نقدی.
 *
 * تعاریف:
 * - وجه دریافت‌شده: جمع پرداخت‌های تأییدشده درگاه با زمان تأیید در بازه
 * - بازپرداخت: جمع اجراهای ثبت‌شده بازپرداخت با تاریخ واریز در بازه
 * - خالص دریافتی: وجه دریافت‌شده − بازپرداخت
 * - درآمد شناسایی‌شده: خالص دریافتی ایده‌هایی که وضعیت «عرضه‌شده» دارند
 * - پیش‌دریافت: خالص دریافتی سایر ایده‌ها؛ تعهد به مشتری تا عرضه محصول
 * - هزینه: جمع هزینه‌های ثبت‌شده با تاریخ وقوع در بازه
 * - مانده نقدی: خالص دریافتی − هزینه
 * - در انتظار (بدون فیلتر بازه، وضعیت فعلی): پرداخت در انتظار تأیید درگاه،
 *   درخواست بازپرداخت در انتظار بررسی، و مبلغ تأییدشده‌ای که هنوز واریز نشده
 */
class FinanceReportService
{
    public function build(?Carbon $from, ?Carbon $to, ?int $ideaId): array
    {
        $tz = config('app.display_timezone');
        $fromUtc = $from?->copy()->timezone($tz)->startOfDay()->utc();
        $toUtc = $to?->copy()->timezone($tz)->endOfDay()->utc();

        $received = Payment::query()
            ->join('preorders', 'preorders.id', '=', 'payments.preorder_id')
            ->join('landing_pages', 'landing_pages.id', '=', 'preorders.landing_page_id')
            ->where('payments.status', Payment::STATUS_PAID)
            ->when($fromUtc, fn ($q) => $q->where('payments.verified_at', '>=', $fromUtc))
            ->when($toUtc, fn ($q) => $q->where('payments.verified_at', '<=', $toUtc))
            ->when($ideaId, fn ($q) => $q->where('landing_pages.idea_id', $ideaId))
            ->groupBy('landing_pages.idea_id')
            ->selectRaw('landing_pages.idea_id AS idea_id, SUM(payments.amount_rial) AS total')
            ->pluck('total', 'idea_id');

        $refunded = Refund::query()
            ->join('payments', 'payments.id', '=', 'refunds.payment_id')
            ->join('preorders', 'preorders.id', '=', 'payments.preorder_id')
            ->join('landing_pages', 'landing_pages.id', '=', 'preorders.landing_page_id')
            ->when($from, fn ($q) => $q->whereDate('refunds.refunded_on', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('refunds.refunded_on', '<=', $to->toDateString()))
            ->when($ideaId, fn ($q) => $q->where('landing_pages.idea_id', $ideaId))
            ->groupBy('landing_pages.idea_id')
            ->selectRaw('landing_pages.idea_id AS idea_id, SUM(refunds.amount_rial) AS total')
            ->pluck('total', 'idea_id');

        $expenseQuery = Expense::query()
            ->when($from, fn ($q) => $q->whereDate('incurred_on', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('incurred_on', '<=', $to->toDateString()))
            ->when($ideaId, fn ($q) => $q->where('idea_id', $ideaId));

        $expenses = (clone $expenseQuery)
            ->groupBy('idea_id')
            ->selectRaw('idea_id, SUM(amount_rial) AS total')
            ->pluck('total', 'idea_id');

        $expensesByCategory = (clone $expenseQuery)
            ->groupBy('category')
            ->selectRaw('category, SUM(amount_rial) AS total')
            ->pluck('total', 'category')
            ->map(fn ($v) => (int) $v);

        $expensesByType = (clone $expenseQuery)
            ->groupBy('cost_type')
            ->selectRaw('cost_type, SUM(amount_rial) AS total')
            ->pluck('total', 'cost_type')
            ->map(fn ($v) => (int) $v);

        $ideaIds = collect($received->keys())->merge($refunded->keys())->merge($expenses->keys())->unique();
        $ideas = Idea::whereIn('id', $ideaIds->filter())->get(['id', 'name', 'status'])->keyBy('id');

        $rows = $ideaIds->map(function ($id) use ($received, $refunded, $expenses, $ideas) {
            $idea = $id ? $ideas->get($id) : null;
            $net = (int) ($received[$id] ?? 0) - (int) ($refunded[$id] ?? 0);
            $launched = $idea?->status === IdeaStatus::Launched->value;

            return [
                'idea' => $idea,
                'received' => (int) ($received[$id] ?? 0),
                'refunded' => (int) ($refunded[$id] ?? 0),
                'net' => $net,
                'recognized' => $launched ? $net : 0,
                'deferred' => $launched ? 0 : $net,
                'expenses' => (int) ($expenses[$id] ?? 0),
                'cash' => $net - (int) ($expenses[$id] ?? 0),
            ];
        })->sortByDesc('net')->values();

        $totals = collect(['received', 'refunded', 'net', 'recognized', 'deferred', 'expenses', 'cash'])
            ->mapWithKeys(fn ($key) => [$key => (int) $rows->sum($key)])
            ->all();

        return [
            'rows' => $rows,
            'totals' => $totals,
            'expensesByCategory' => $expensesByCategory,
            'expensesByType' => $expensesByType,
            'pending' => $this->pending($ideaId),
        ];
    }

    private function pending(?int $ideaId): array
    {
        $byIdea = fn ($q, string $preorderColumn) => $q->when($ideaId, fn ($q) => $q->whereExists(
            fn ($sub) => $sub->selectRaw('1')
                ->from('preorders')
                ->join('landing_pages', 'landing_pages.id', '=', 'preorders.landing_page_id')
                ->whereColumn('preorders.id', $preorderColumn)
                ->where('landing_pages.idea_id', $ideaId)
        ));

        $pendingPayments = $byIdea(Payment::where('status', Payment::STATUS_PENDING)->whereNotNull('authority'), 'payments.preorder_id')
            ->sum('amount_rial');

        $pendingRequests = $byIdea(RefundRequest::where('status', RefundRequest::STATUS_PENDING), 'refund_requests.preorder_id')
            ->sum('requested_amount_rial');

        $approved = $byIdea(RefundRequest::where('status', RefundRequest::STATUS_APPROVED), 'refund_requests.preorder_id')
            ->get(['id', 'approved_amount_rial']);

        $executed = Refund::whereIn('refund_request_id', $approved->pluck('id'))
            ->groupBy('refund_request_id')
            ->select('refund_request_id', DB::raw('SUM(amount_rial) AS total'))
            ->pluck('total', 'refund_request_id');

        $approvedUnpaid = $approved->sum(fn ($r) => $r->approved_amount_rial - (int) ($executed[$r->id] ?? 0));

        return [
            'payments' => (int) $pendingPayments,
            'refund_requests' => (int) $pendingRequests,
            'approved_unpaid' => (int) $approvedUnpaid,
        ];
    }
}

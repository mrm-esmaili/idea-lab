<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * گزارش تراکنش‌ها (مرحله ۴۸).
 *
 * تعاریف:
 * - موفق: تأییدشده سمت سرور درگاه (status = paid)
 * - در انتظار: authority گرفته شده یا در حال گرفتن، نتیجه هنوز قطعی نیست
 * - ناموفق: درگاه پرداخت را رد کرد؛ لغوشده: کاربر یا درگاه در صفحه پرداخت لغو کرد
 * - پرداخت تکراری: سفارشی که بیش از یک تراکنش موفق دارد (نیازمند بازپرداخت)
 * بازه تاریخ بر اساس زمان ایجاد تراکنش و منطقه زمانی نمایش است.
 */
class PaymentReportController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(config('statuses.payment')))],
            'idea' => ['nullable', 'integer', 'exists:ideas,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'duplicates' => ['nullable', 'boolean'],
        ]);

        $base = $this->filtered($filters);

        $summary = (clone $base)
            ->select('payments.status')
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('COALESCE(SUM(payments.amount_rial), 0) AS amount_rial')
            ->groupBy('payments.status')
            ->get()
            ->keyBy('status');

        $duplicatePreorderIds = $this->duplicatePreorderIds();

        $listQuery = (clone $base)
            ->select('payments.*')
            ->with('preorder.landingPage.idea')
            ->latest('payments.id');

        if (! empty($filters['status'])) {
            $listQuery->where('payments.status', $filters['status']);
        }

        if ($request->boolean('duplicates')) {
            $listQuery->whereIn('payments.preorder_id', $duplicatePreorderIds)
                ->where('payments.status', Payment::STATUS_PAID);
        }

        $payments = $listQuery->paginate(30)->withQueryString();

        $ideas = Idea::orderBy('name')->get(['id', 'name']);

        return view('admin.payments.index', [
            'payments' => $payments,
            'summary' => $summary,
            'ideas' => $ideas,
            'duplicateCount' => count($duplicatePreorderIds),
            'duplicatePreorderIds' => $duplicatePreorderIds,
        ]);
    }

    /**
     * فیلترهای ایده و تاریخ؛ فیلتر وضعیت فقط روی فهرست اعمال می‌شود تا
     * خلاصه همه وضعیت‌ها را نشان دهد.
     */
    private function filtered(array $filters): Builder
    {
        $query = Payment::query()
            ->join('preorders', 'preorders.id', '=', 'payments.preorder_id')
            ->join('landing_pages', 'landing_pages.id', '=', 'preorders.landing_page_id');

        if (! empty($filters['idea'])) {
            $query->where('landing_pages.idea_id', $filters['idea']);
        }

        $timezone = config('app.display_timezone');

        if (! empty($filters['from'])) {
            $query->where(
                'payments.created_at',
                '>=',
                Carbon::parse($filters['from'], $timezone)->startOfDay()->utc()
            );
        }

        if (! empty($filters['to'])) {
            $query->where(
                'payments.created_at',
                '<=',
                Carbon::parse($filters['to'], $timezone)->endOfDay()->utc()
            );
        }

        return $query;
    }

    /**
     * @return list<int>
     */
    private function duplicatePreorderIds(): array
    {
        return DB::table('payments')
            ->where('status', Payment::STATUS_PAID)
            ->groupBy('preorder_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('preorder_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

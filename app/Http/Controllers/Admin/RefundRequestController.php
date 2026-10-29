<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\Preorder;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Services\AuditLogger;
use App\Services\Refunds\RefundException;
use App\Services\Refunds\RefundService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RefundRequestController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(config('statuses.refund_request')))],
            'idea' => ['nullable', 'integer', 'exists:ideas,id'],
        ]);

        $query = RefundRequest::with(['preorder.landingPage.idea', 'payment'])
            ->latest('id');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['idea'])) {
            $query->whereHas('preorder.landingPage', fn ($q) => $q->where('idea_id', $filters['idea']));
        }

        return view('admin.refunds.index', [
            'requests' => $query->paginate(30)->withQueryString(),
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
            'counts' => RefundRequest::selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(RefundRequest $refundRequest, RefundService $refunds, AuditLogger $audit)
    {
        $refundRequest->load(['preorder.landingPage.idea', 'preorder.contact', 'payment', 'refundPolicy', 'refunds.executor', 'decider', 'requester']);

        $payment = $refundRequest->payment;
        $policy = $refundRequest->refundPolicy;

        return view('admin.refunds.show', [
            'refundRequest' => $refundRequest,
            'paymentRemaining' => $refunds->remaining($payment),
            'executed' => $refundRequest->executedAmount(),
            'deadline' => $policy?->refund_window_days
                ? Carbon::instance($payment->verified_at ?? $payment->created_at)->addDays($policy->refund_window_days)
                : null,
            'auditLogs' => $audit->for($refundRequest),
        ]);
    }

    /**
     * ثبت درخواست توسط مدیر (مثلاً بازپرداخت پرداخت تکراری یا درخواست تلفنی مشتری).
     */
    public function store(Request $request, Preorder $preorder, RefundService $refunds)
    {
        $data = $this->moneyRequest($request, [
            'payment_id' => ['required', 'integer'],
            'amount_toman' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $payment = $preorder->payments()->whereKey($data['payment_id'])->firstOrFail();

        $refundRequest = $this->attempt(fn () => $refunds->request(
            $preorder,
            $payment,
            Money::tomanToRial((int) $data['amount_toman']),
            $data['reason'],
            $request->user(),
        ));

        return redirect()
            ->route('admin.refunds.show', $refundRequest)
            ->with('success', 'درخواست بازپرداخت ثبت شد و در انتظار تأیید است.');
    }

    public function approve(Request $request, RefundRequest $refundRequest, RefundService $refunds)
    {
        $data = $this->moneyRequest($request, [
            'amount_toman' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->attempt(fn () => $refunds->approve(
            $refundRequest,
            Money::tomanToRial((int) $data['amount_toman']),
            $data['note'] ?? null,
            $request->user(),
        ));

        return back()->with('success', 'درخواست تأیید شد. پس از واریز، اجرای بازپرداخت را با مرجع ثبت کنید.');
    }

    public function reject(Request $request, RefundRequest $refundRequest, RefundService $refunds)
    {
        $data = $request->validate([
            'note' => ['required', 'string', 'min:3', 'max:2000'],
        ], [], ['note' => 'دلیل رد']);

        $this->attempt(fn () => $refunds->reject($refundRequest, $data['note'], $request->user()));

        return back()->with('success', 'درخواست رد شد.');
    }

    public function execute(Request $request, RefundRequest $refundRequest, RefundService $refunds)
    {
        $data = $this->moneyRequest($request, [
            'amount_toman' => ['required', 'integer', 'min:1'],
            'method' => ['required', Rule::in(array_keys(Refund::METHODS))],
            'reference' => ['required', 'string', 'max:100'],
            'refunded_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:2000'],
        ], ['reference' => 'مرجع / شماره پیگیری', 'refunded_on' => 'تاریخ واریز', 'method' => 'روش']);

        $this->attempt(fn () => $refunds->execute(
            $refundRequest,
            Money::tomanToRial((int) $data['amount_toman']),
            $data['method'],
            trim($data['reference']),
            Carbon::parse($data['refunded_on']),
            $data['note'] ?? null,
            $request->user(),
        ));

        return back()->with('success', 'اجرای بازپرداخت ثبت شد.');
    }

    private function moneyRequest(Request $request, array $rules, array $attributes = []): array
    {
        $request->merge(['amount_toman' => Money::normalizeInput($request->input('amount_toman'))]);

        return $request->validate($rules, [], $attributes + ['amount_toman' => 'مبلغ']);
    }

    private function attempt(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (RefundException $e) {
            throw ValidationException::withMessages(['refund' => $e->getMessage()]);
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\Payment;
use App\Models\Preorder;
use App\Services\Payments\PreorderPaymentService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreorderManagementController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => [
                'nullable',
                Rule::in(array_keys(config('statuses.preorder'))),
            ],
            'payment_status' => [
                'nullable',
                Rule::in(array_keys(config('statuses.preorder_payment'))),
            ],
            'idea' => ['nullable', 'integer', 'exists:ideas,id'],
        ]);

        $query = Preorder::with([
            'landingPage.idea',
            'refundPolicy',
        ])->latest();

        if (!empty($data['idea'])) {
            $query->whereHas(
                'landingPage',
                fn ($page) => $page->where('idea_id', $data['idea'])
            );
        }

        if (!empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        if (!empty($data['payment_status'])) {
            $query->where('payment_status', $data['payment_status']);
        }

        $preorders = $query
            ->paginate(20)
            ->withQueryString();

        $ideas = Idea::orderBy('name')->get(['id', 'name']);

        return view(
            'admin.preorders.index',
            compact('preorders', 'ideas')
        );
    }

    public function show(Preorder $preorder)
    {
        $preorder->load([
            'landingPage.idea',
            'refundPolicy',
            'lead',
            'payments' => fn ($query) => $query->latest('id'),
        ]);

        return view('admin.preorders.show', compact('preorder'));
    }

    /**
     * فقط وضعیت پیگیری فروش؛ وضعیت مالی را فقط سیستم پرداخت تغییر می‌دهد.
     */
    public function update(Request $request, Preorder $preorder)
    {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(array_keys(config('statuses.preorder'))),
            ],
        ]);

        $preorder->update($data);

        return redirect()
            ->back(fallback: route('admin.preorders.index'))
            ->with('success', 'وضعیت پیگیری به‌روزرسانی شد.');
    }

    /**
     * استعلام دوباره تراکنش در انتظار (مثلاً وقتی مشتری می‌گوید وجه کسر شده).
     */
    public function verifyPayment(
        Payment $payment,
        PreorderPaymentService $payments
    ) {
        if ($payment->status !== Payment::STATUS_PENDING || ! $payment->authority) {
            return redirect()
                ->route('admin.preorders.show', $payment->preorder_id)
                ->withErrors(['payment' => 'فقط تراکنش در انتظارِ دارای شناسه درگاه قابل استعلام است.']);
        }

        $payment = $payments->verify($payment);

        $message = match ($payment->status) {
            Payment::STATUS_PAID => 'پرداخت تأیید شد. کد پیگیری: ' . $payment->ref_id,
            Payment::STATUS_FAILED => 'درگاه پرداخت را تأیید نکرد: ' . $payment->failure_reason,
            default => 'پاسخ قطعی از درگاه دریافت نشد؛ بعداً دوباره استعلام کنید.',
        };

        return redirect()
            ->route('admin.preorders.show', $payment->preorder_id)
            ->with('success', $message);
    }
}

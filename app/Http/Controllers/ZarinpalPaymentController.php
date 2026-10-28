<?php

namespace App\Http\Controllers;

use App\Models\Preorder;
use App\Services\Payments\PreorderPaymentService;
use Illuminate\Http\Request;

class ZarinpalPaymentController extends Controller
{
    public function checkout(string $token)
    {
        $preorder = $this->findPreorder($token);

        if ($preorder->isPaid()) {
            return redirect()->route('preorders.receipt', $token);
        }

        $preorder->load('refundPolicy');

        return view(
            'payments.checkout',
            compact('preorder')
        );
    }

    public function pay(
        string $token,
        PreorderPaymentService $payments
    ) {
        $preorder = $this->findPreorder($token);

        $result = $payments->start($preorder);

        return match ($result['action']) {
            'paid' => redirect()->route('preorders.receipt', $token),
            'redirect' => redirect()->away($result['url']),
            'busy' => redirect()
                ->route('preorders.checkout', $token)
                ->with('payment_notice', 'درخواست پرداخت شما در حال ارسال به درگاه است؛ چند لحظه بعد دوباره تلاش کنید.'),
            'error' => redirect()
                ->route('preorders.checkout', $token)
                ->with('payment_error', 'اتصال به درگاه پرداخت ممکن نشد. لطفاً دوباره تلاش کنید.'),
        };
    }

    public function callback(
        Request $request,
        PreorderPaymentService $payments
    ) {
        $payment = $payments->handleCallback(
            $request->query('Authority'),
            $request->query('Status')
        );

        if (! $payment) {
            return redirect()->route('payments.result', ['status' => 'failed']);
        }

        return redirect()->route(
            'preorders.receipt',
            $payment->preorder->public_token
        );
    }

    /**
     * وضعیت واقعی از پایگاه داده خوانده می‌شود، نه از پارامتر آدرس.
     */
    public function receipt(string $token)
    {
        $preorder = $this->findPreorder($token);

        $preorder->load(['landingPage.idea', 'refundPolicy']);

        $payment = $preorder->payments()
            ->orderByRaw("CASE WHEN status = 'paid' THEN 0 ELSE 1 END")
            ->latest('id')
            ->first();

        return view('payments.receipt', compact('preorder', 'payment'));
    }

    /**
     * فقط برای بازگشتی که تراکنش آن شناخته نشد.
     */
    public function result(Request $request)
    {
        $status = $request->query('status');

        if (!in_array($status, [
            'success',
            'failed',
            'error',
        ], true)) {
            $status = 'error';
        }

        return view(
            'payments.result',
            compact('status')
        );
    }

    private function findPreorder(string $token): Preorder
    {
        abort_unless(preg_match('/^[0-9a-f-]{36}$/i', $token), 404);

        $preorder = Preorder::where('public_token', $token)->firstOrFail();

        if (! $preorder->amount_rial || $preorder->amount_rial < 1) {
            abort(422, 'مبلغ پیش‌خرید تنظیم نشده است.');
        }

        return $preorder;
    }
}

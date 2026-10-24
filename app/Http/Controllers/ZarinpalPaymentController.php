<?php

namespace App\Http\Controllers;

use App\Models\LandingPageEvent;
use App\Models\Payment;
use App\Models\Preorder;
use App\Services\ZarinpalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class ZarinpalPaymentController extends Controller
{
    public function checkout(string $token)
    {
        $preorder = Preorder::where(
            'public_token',
            $token
        )->firstOrFail();

        if (!$preorder->amount || $preorder->amount < 1) {
            abort(422, 'مبلغ پیش‌خرید تنظیم نشده است.');
        }

        return view(
            'payments.checkout',
            compact('preorder')
        );
    }

    public function pay(
        Request $request,
        string $token,
        ZarinpalService $zarinpal
    ) {
        $preorder = Preorder::where(
            'public_token',
            $token
        )->firstOrFail();

        if (!$preorder->amount || $preorder->amount < 1) {
            abort(422, 'مبلغ پیش‌خرید تنظیم نشده است.');
        }

        $paidPayment = $preorder->payments()
            ->where('status', 'paid')
            ->first();

        if ($paidPayment) {
            return redirect()->route(
                'payments.result',
                ['status' => 'success']
            );
        }

        $amountRial = ((int) $preorder->amount) * 10;

        $payment = Payment::create([
            'preorder_id' => $preorder->id,
            'gateway' => 'zarinpal',
            'amount_rial' => $amountRial,
            'status' => 'pending',
        ]);

        try {
            $result = $zarinpal->requestPayment(
                $amountRial,
                route('payments.zarinpal.callback'),
                'Preorder #' . $preorder->id,
                $preorder->phone
            );

            $payment->update([
                'authority' => $result['authority'],
            ]);

            return redirect()->away(
                $result['payment_url']
            );
        } catch (Throwable $e) {
            $payment->update([
                'status' => 'failed',
            ]);

            Log::error('Zarinpal request failed.', [
                'payment_id' => $payment->id,
                'exception' => $e->getMessage(),
            ]);

            return redirect()
                ->route('payments.result', [
                    'status' => 'error',
                ]);
        }
    }

    public function callback(
        Request $request,
        ZarinpalService $zarinpal
    ) {
        $authority = $request->query('Authority');
        $gatewayStatus = $request->query('Status');

        if (!is_string($authority) || $authority === '') {
            return redirect()->route(
                'payments.result',
                ['status' => 'failed']
            );
        }

        $payment = Payment::where(
            'authority',
            $authority
        )->first();

        if (!$payment) {
            return redirect()->route(
                'payments.result',
                ['status' => 'failed']
            );
        }

        if ($payment->status === 'paid') {
            return redirect()->route(
                'payments.result',
                ['status' => 'success']
            );
        }

        if ($gatewayStatus !== 'OK') {
            $payment->update([
                'status' => 'failed',
            ]);

            return redirect()->route(
                'payments.result',
                ['status' => 'failed']
            );
        }

        try {
            $verification = $zarinpal->verifyPayment(
                $payment->authority,
                $payment->amount_rial
            );

            if (empty($verification['ref_id'])) {
                throw new RuntimeException(
                    'Zarinpal returned no reference ID.'
                );
            }

            DB::transaction(function () use (
                $payment,
                $verification
            ) {
                $lockedPayment = Payment::whereKey(
                    $payment->id
                )->lockForUpdate()->firstOrFail();

                if ($lockedPayment->status === 'paid') {
                    return;
                }

                $lockedPayment->update([
                    'status' => 'paid',
                    'ref_id' => (string) $verification['ref_id'],
                    'verified_at' => now(),
                ]);

                $preorder = Preorder::findOrFail(
                    $lockedPayment->preorder_id
                );

                $preorder->update([
                    'status' => 'converted',
                ]);

                $eventData = [
                    'landing_page_id' => $preorder->landing_page_id,
                    'visitor_id' => $preorder->visitor_id,
                    'event_name' => 'payment_success',
                    'source' => $preorder->source,
                    'medium' => $preorder->medium,
                    'campaign' => $preorder->campaign,
                    'occurred_at' => now(),
                ];

                LandingPageEvent::create($eventData);
            });

            return redirect()->route(
                'payments.result',
                ['status' => 'success']
            );
        } catch (Throwable $e) {
            Log::error('Zarinpal verification failed.', [
                'payment_id' => $payment->id,
                'exception' => $e->getMessage(),
            ]);

            return redirect()->route(
                'payments.result',
                ['status' => 'error']
            );
        }
    }

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
}

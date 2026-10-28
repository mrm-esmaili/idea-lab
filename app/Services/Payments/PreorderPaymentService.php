<?php

namespace App\Services\Payments;

use App\Models\LandingPageEvent;
use App\Models\Payment;
use App\Models\Preorder;
use App\Services\ZarinpalService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * چرخه پرداخت پیش‌خرید.
 *
 * قواعد:
 * - وضعیت مالی سفارش (payment_status) فقط پس از تأیید سمت سرور درگاه
 *   «paid» می‌شود؛ بازگشت مرورگر به‌تنهایی کافی نیست.
 * - هر انتقال وضعیت تراکنش شرطی است (فقط از pending) و زیر قفل ردیف
 *   انجام می‌شود؛ callback تکراری یا هم‌زمان اثر دوباره ندارد.
 * - تا ۱۰ دقیقه، درخواست پرداخت دوباره همان authority در انتظار را
 *   استفاده می‌کند تا چند تراکنش هم‌زمان برای یک سفارش ساخته نشود.
 * - اگر با وجود این، دو تراکنش یک سفارش موفق شوند، هر دو «paid» ثبت
 *   می‌شوند (پول واقعاً دریافت شده) و در گزارش پرداخت‌ها به‌عنوان
 *   پرداخت تکراری نیازمند بازپرداخت نمایش داده می‌شوند.
 */
class PreorderPaymentService
{
    public const PENDING_REUSE_MINUTES = 10;

    public function __construct(
        private readonly ZarinpalService $zarinpal,
    ) {}

    /**
     * @return array{action: 'paid'|'redirect'|'busy'|'error', url?: string}
     */
    public function start(Preorder $preorder): array
    {
        $decision = DB::transaction(function () use ($preorder) {
            $locked = Preorder::whereKey($preorder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->isPaid()) {
                return ['paid'];
            }

            $pending = $locked->payments()
                ->where('status', Payment::STATUS_PENDING)
                ->where('created_at', '>=', now()->subMinutes(self::PENDING_REUSE_MINUTES))
                ->latest('id')
                ->first();

            if ($pending?->authority) {
                return ['reuse', $pending];
            }

            if ($pending) {
                // درخواست دیگری همین الان در حال گرفتن authority است
                return ['busy'];
            }

            return ['new', $locked->payments()->create([
                'gateway' => 'zarinpal',
                'amount_rial' => $locked->amount_rial,
                'status' => Payment::STATUS_PENDING,
            ])];
        });

        return match ($decision[0]) {
            'paid' => ['action' => 'paid'],
            'busy' => ['action' => 'busy'],
            'reuse' => [
                'action' => 'redirect',
                'url' => $this->zarinpal->startPayUrl($decision[1]->authority),
            ],
            'new' => $this->requestAuthority($preorder, $decision[1]),
        };
    }

    private function requestAuthority(Preorder $preorder, Payment $payment): array
    {
        try {
            $result = $this->zarinpal->requestPayment(
                $payment->amount_rial,
                route('payments.zarinpal.callback'),
                'Preorder #' . $preorder->id,
                $preorder->phone
            );
        } catch (Throwable $e) {
            $this->markFinished($payment, Payment::STATUS_FAILED, [
                'failure_reason' => $this->reason('درخواست پرداخت', $e),
                'gateway_response' => $e instanceof ZarinpalException ? $e->response : null,
            ]);

            Log::error('Zarinpal request failed.', [
                'payment_id' => $payment->id,
                'exception' => $e->getMessage(),
            ]);

            return ['action' => 'error'];
        }

        try {
            $payment->update([
                'authority' => $result['authority'],
                'gateway_response' => $result['response'],
            ]);
        } catch (UniqueConstraintViolationException) {
            // authority تکراری از درگاه نباید به تراکنش دیگری وصل شود
            $this->markFinished($payment, Payment::STATUS_FAILED, [
                'failure_reason' => 'درگاه شناسه پرداخت تکراری برگرداند.',
            ]);

            Log::error('Zarinpal returned a duplicate authority.', [
                'payment_id' => $payment->id,
            ]);

            return ['action' => 'error'];
        }

        return ['action' => 'redirect', 'url' => $result['payment_url']];
    }

    /**
     * پردازش بازگشت از درگاه؛ null یعنی تراکنشی با این authority وجود ندارد.
     */
    public function handleCallback(mixed $authority, mixed $gatewayStatus): ?Payment
    {
        if (
            ! is_string($authority)
            || strlen($authority) > 100
            || ! preg_match('/^[A-Za-z0-9]+$/', $authority)
        ) {
            return null;
        }

        $payment = Payment::where('authority', $authority)->first();

        if (! $payment) {
            return null;
        }

        if ($payment->status !== Payment::STATUS_PENDING) {
            return $payment;
        }

        if ($gatewayStatus !== 'OK') {
            $this->markFinished($payment, Payment::STATUS_CANCELLED, [
                'failure_reason' => 'پرداخت توسط کاربر یا درگاه لغو شد.',
            ]);

            return $payment->fresh();
        }

        return $this->verify($payment);
    }

    /**
     * استعلام و تأیید سمت سرور؛ از callback و استعلام دستی مدیر استفاده می‌شود.
     */
    public function verify(Payment $payment): Payment
    {
        if ($payment->status !== Payment::STATUS_PENDING || ! $payment->authority) {
            return $payment;
        }

        try {
            $result = $this->zarinpal->verifyPayment(
                $payment->authority,
                $payment->amount_rial
            );
        } catch (Throwable $e) {
            if ($e instanceof ZarinpalException && $e->isDefinitive()) {
                $this->markFinished($payment, Payment::STATUS_FAILED, [
                    'failure_reason' => $this->reason('تأیید پرداخت', $e),
                    'gateway_response' => $e->response,
                ]);
            } else {
                // نتیجه نامعلوم؛ در انتظار می‌ماند تا دوباره استعلام شود
                Payment::whereKey($payment->id)
                    ->where('status', Payment::STATUS_PENDING)
                    ->update(['failure_reason' => $this->reason('استعلام (نامعلوم، قابل تکرار)', $e)]);
            }

            Log::warning('Zarinpal verification failed.', [
                'payment_id' => $payment->id,
                'gateway_code' => $e instanceof ZarinpalException ? $e->gatewayCode : null,
                'exception' => $e->getMessage(),
            ]);

            return $payment->fresh();
        }

        DB::transaction(function () use ($payment, $result) {
            $locked = Payment::whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== Payment::STATUS_PENDING) {
                return;
            }

            $locked->update([
                'status' => Payment::STATUS_PAID,
                'ref_id' => $result['ref_id'],
                'card_pan' => $result['card_pan'],
                'failure_reason' => null,
                'gateway_response' => $result['response'],
                'verified_at' => now(),
            ]);

            $preorder = Preorder::whereKey($locked->preorder_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($preorder->isPaid()) {
                Log::warning('Duplicate successful payment for preorder.', [
                    'preorder_id' => $preorder->id,
                    'payment_id' => $locked->id,
                ]);

                return;
            }

            $preorder->update(['payment_status' => Preorder::PAYMENT_PAID]);

            LandingPageEvent::create([
                'landing_page_id' => $preorder->landing_page_id,
                'visitor_id' => $preorder->visitor_id,
                'event_name' => 'payment_success',
                'source' => $preorder->source,
                'medium' => $preorder->medium,
                'campaign' => $preorder->campaign,
                'occurred_at' => now(),
            ]);
        });

        return $payment->fresh();
    }

    private function markFinished(Payment $payment, string $status, array $attributes): void
    {
        Payment::whereKey($payment->id)
            ->where('status', Payment::STATUS_PENDING)
            ->update(['status' => $status] + array_map(
                fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : $value,
                $attributes
            ));
    }

    private function reason(string $stage, Throwable $e): string
    {
        $code = $e instanceof ZarinpalException && $e->gatewayCode !== null
            ? " (کد {$e->gatewayCode})"
            : '';

        $message = $e instanceof ZarinpalException
            ? ($e->response['errors']['message'] ?? $e->response['data']['message'] ?? $e->getMessage())
            : $e->getMessage();

        return Str::limit("{$stage}{$code}: {$message}", 250);
    }
}

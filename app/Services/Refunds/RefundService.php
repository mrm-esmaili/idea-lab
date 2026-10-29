<?php

namespace App\Services\Refunds;

use App\Models\Contact;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * قواعد بازپرداخت (مراحل ۵۰ تا ۵۵).
 *
 * - درخواست، تصمیم مدیر و اجرای واقعی سه مرحله جدا هستند.
 * - وضعیت «بازپرداخت‌شده» فقط پس از ثبت اجرای واقعی با مرجع اعمال می‌شود.
 * - جمع بازپرداخت‌های یک پرداخت هرگز از مبلغ آن بیشتر نمی‌شود؛ همه
 *   بررسی‌ها زیر قفل ردیف پرداخت انجام می‌شوند.
 * - هر پرداخت حداکثر یک درخواست باز دارد.
 * - همه عملیات در audit_logs ثبت می‌شود.
 */
class RefundService
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * مبلغ قابل بازپرداخت باقی‌مانده یک پرداخت (پس از کسر اجراهای ثبت‌شده).
     */
    public function remaining(Payment $payment): int
    {
        return max(0, $payment->amount_rial - (int) Refund::where('payment_id', $payment->id)->sum('amount_rial'));
    }

    /**
     * بررسی امکان درخواست آنلاین مشتری بر اساس شرایطی که هنگام خرید پذیرفته است.
     *
     * @return array{eligible: bool, reason: ?string, payment: ?Payment, max_rial: int, deadline: ?Carbon}
     */
    public function customerEligibility(Preorder $preorder): array
    {
        $result = fn (bool $eligible, ?string $reason, ?Payment $payment = null, int $max = 0, ?Carbon $deadline = null) => [
            'eligible' => $eligible,
            'reason' => $reason,
            'payment' => $payment,
            'max_rial' => $max,
            'deadline' => $deadline,
        ];

        if (! in_array($preorder->payment_status, [Preorder::PAYMENT_PAID, Preorder::PAYMENT_PARTIALLY_REFUNDED], true)) {
            return $result(false, null);
        }

        if ($preorder->refundRequests()->whereIn('status', RefundRequest::OPEN)->exists()) {
            return $result(false, 'درخواست بازپرداخت شما در حال بررسی است.');
        }

        $policy = $preorder->refundPolicy;

        if (! $policy || ! $policy->is_refundable) {
            return $result(false, 'طبق شرایط پذیرفته‌شده، این سفارش بازپرداخت آنلاین ندارد. برای موارد خاص با پشتیبانی تماس بگیرید.');
        }

        $payment = $preorder->payments()
            ->where('status', Payment::STATUS_PAID)
            ->latest('id')
            ->get()
            ->first(fn (Payment $payment) => $this->remaining($payment) > 0);

        if (! $payment) {
            return $result(false, null);
        }

        $deadline = $policy->refund_window_days
            ? Carbon::instance($payment->verified_at ?? $payment->created_at)->addDays($policy->refund_window_days)
            : null;

        if ($deadline && now()->gt($deadline)) {
            return $result(false, 'مهلت درخواست بازپرداخت طبق شرایط پذیرفته‌شده به پایان رسیده است.', $payment, 0, $deadline);
        }

        return $result(true, null, $payment, $this->remaining($payment), $deadline);
    }

    /**
     * @throws RefundException
     */
    public function request(
        Preorder $preorder,
        Payment $payment,
        int $amountRial,
        string $reason,
        User|Contact $actor,
    ): RefundRequest {
        if ($payment->preorder_id !== $preorder->id || $payment->status !== Payment::STATUS_PAID) {
            throw new RefundException('فقط پرداخت موفق همین سفارش قابل بازپرداخت است.');
        }

        return DB::transaction(function () use ($preorder, $payment, $amountRial, $reason, $actor) {
            $locked = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (RefundRequest::where('payment_id', $locked->id)->whereIn('status', RefundRequest::OPEN)->exists()) {
                throw new RefundException('برای این پرداخت یک درخواست بازپرداخت باز وجود دارد.');
            }

            $remaining = $this->remaining($locked);

            if ($amountRial < 1 || $amountRial > $remaining) {
                throw new RefundException('مبلغ درخواست بیشتر از مبلغ قابل بازپرداخت است.');
            }

            $request = RefundRequest::create([
                'preorder_id' => $preorder->id,
                'payment_id' => $locked->id,
                'contact_id' => $preorder->contact_id,
                'refund_policy_id' => $preorder->refund_policy_id,
                'requested_by_type' => $actor instanceof User ? 'admin' : 'customer',
                'requested_by_user_id' => $actor instanceof User ? $actor->id : null,
                'requested_amount_rial' => $amountRial,
                'reason' => $reason,
                'status' => RefundRequest::STATUS_PENDING,
            ]);

            $this->audit->log($request, 'refund.requested', [
                'amount_rial' => $amountRial,
                'payment_id' => $locked->id,
                'remaining_rial' => $remaining,
            ], $actor);

            return $request;
        });
    }

    /**
     * @throws RefundException
     */
    public function approve(RefundRequest $request, int $amountRial, ?string $note, User $user): RefundRequest
    {
        return DB::transaction(function () use ($request, $amountRial, $note, $user) {
            Payment::whereKey($request->payment_id)->lockForUpdate()->firstOrFail();
            $locked = RefundRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== RefundRequest::STATUS_PENDING) {
                throw new RefundException('فقط درخواست در انتظار بررسی قابل تأیید است.');
            }

            $remaining = $this->remaining($locked->payment);

            if ($amountRial < 1 || $amountRial > $locked->requested_amount_rial || $amountRial > $remaining) {
                throw new RefundException('مبلغ تأییدشده باید بین ۱ و کمترینِ مبلغ درخواست و مبلغ قابل بازپرداخت باشد.');
            }

            $locked->update([
                'status' => RefundRequest::STATUS_APPROVED,
                'approved_amount_rial' => $amountRial,
                'decision_note' => $note,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);

            $this->audit->log($locked, 'refund.approved', ['approved_amount_rial' => $amountRial, 'note' => $note], $user);

            return $locked;
        });
    }

    /**
     * @throws RefundException
     */
    public function reject(RefundRequest $request, string $note, User $user): RefundRequest
    {
        return DB::transaction(function () use ($request, $note, $user) {
            $locked = RefundRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== RefundRequest::STATUS_PENDING) {
                throw new RefundException('فقط درخواست در انتظار بررسی قابل رد است.');
            }

            $locked->update([
                'status' => RefundRequest::STATUS_REJECTED,
                'decision_note' => $note,
                'decided_by' => $user->id,
                'decided_at' => now(),
            ]);

            $this->audit->log($locked, 'refund.rejected', ['note' => $note], $user);

            return $locked;
        });
    }

    /**
     * ثبت اجرای واقعی بازپرداخت (مرحله ۵۳).
     *
     * @throws RefundException
     */
    public function execute(
        RefundRequest $request,
        int $amountRial,
        string $method,
        string $reference,
        Carbon $refundedOn,
        ?string $note,
        User $user,
    ): Refund {
        return DB::transaction(function () use ($request, $amountRial, $method, $reference, $refundedOn, $note, $user) {
            $payment = Payment::whereKey($request->payment_id)->lockForUpdate()->firstOrFail();
            $locked = RefundRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== RefundRequest::STATUS_APPROVED) {
                throw new RefundException('فقط درخواست تأییدشده قابل اجراست.');
            }

            $approvedRemaining = $locked->approved_amount_rial - $locked->executedAmount();
            $paymentRemaining = $this->remaining($payment);

            if ($amountRial < 1 || $amountRial > $approvedRemaining || $amountRial > $paymentRemaining) {
                throw new RefundException('مبلغ اجرا بیشتر از باقی‌مانده مبلغ تأییدشده یا مبلغ قابل بازپرداخت است.');
            }

            if (trim($reference) === '') {
                throw new RefundException('مرجع یا شماره پیگیری بازپرداخت الزامی است.');
            }

            if (Refund::where('payment_id', $payment->id)->where('reference', $reference)->exists()) {
                throw new RefundException('بازپرداختی با همین مرجع برای این پرداخت قبلاً ثبت شده است.');
            }

            $refund = $locked->refunds()->create([
                'payment_id' => $payment->id,
                'amount_rial' => $amountRial,
                'method' => $method,
                'reference' => $reference,
                'refunded_on' => $refundedOn,
                'note' => $note,
                'executed_by' => $user->id,
            ]);

            if ($locked->executedAmount() >= $locked->approved_amount_rial) {
                $locked->update(['status' => RefundRequest::STATUS_COMPLETED]);
            }

            $status = $this->syncPreorderStatus(Preorder::whereKey($locked->preorder_id)->lockForUpdate()->firstOrFail());

            $this->audit->log($locked, 'refund.executed', [
                'refund_id' => $refund->id,
                'amount_rial' => $amountRial,
                'method' => $method,
                'reference' => $reference,
                'refunded_on' => $refundedOn->toDateString(),
                'preorder_payment_status' => $status,
            ], $user);

            return $refund;
        });
    }

    public function syncPreorderStatus(Preorder $preorder): string
    {
        $paid = (int) $preorder->payments()->where('status', Payment::STATUS_PAID)->sum('amount_rial');
        $refunded = (int) Refund::whereIn('payment_id', $preorder->payments()->select('id'))->sum('amount_rial');

        $status = match (true) {
            $paid === 0 => Preorder::PAYMENT_UNPAID,
            $refunded >= $paid => Preorder::PAYMENT_REFUNDED,
            $refunded > 0 => Preorder::PAYMENT_PARTIALLY_REFUNDED,
            default => Preorder::PAYMENT_PAID,
        };

        $preorder->update(['payment_status' => $status]);

        return $status;
    }
}

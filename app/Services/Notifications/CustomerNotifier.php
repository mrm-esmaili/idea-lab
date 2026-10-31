<?php

namespace App\Services\Notifications;

use App\Models\Idea;
use App\Models\IdeaStatusChange;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Services\Sms\SmsService;
use App\Support\Money;
use App\Support\PhoneNumber;

/**
 * پیامک‌های تراکنشی به مشتری (مراحل ۵۹ تا ۶۱).
 *
 * فقط پس از ثبت قطعی رویداد در پایگاه داده صدا زده می‌شود. هر پیام کلید
 * یکتای خود را دارد تا تکرار رویداد (callback دوباره، استعلام دستی پس از
 * callback و ...) پیام تکراری نسازد. اگر قالب مربوط غیرفعال باشد پیامی
 * ثبت نمی‌شود.
 */
class CustomerNotifier
{
    /** قالب اطلاع‌رسانی هر وضعیت ایده به خریداران */
    public const IDEA_STATUS_TEMPLATES = [
        'validated' => 'idea_validated',
        'rejected' => 'idea_rejected',
        'launched' => 'idea_launched',
    ];

    public function __construct(
        private readonly SmsService $sms,
    ) {}

    public function paymentPaid(Payment $payment): void
    {
        $preorder = $payment->preorder()->with(['contact', 'landingPage.idea'])->first();

        $this->toCustomer($preorder, 'payment_paid', "payment.paid:{$payment->id}", [
            'amount' => $this->toman($payment->amount_rial),
            'ref_id' => (string) $payment->ref_id,
            'link' => route('account.login'),
        ]);
    }

    public function refundRequested(RefundRequest $request): void
    {
        $this->toCustomer($this->preorderOf($request), 'refund_requested', "refund.requested:{$request->id}", [
            'amount' => $this->toman($request->requested_amount_rial),
        ]);
    }

    public function refundDecided(RefundRequest $request): void
    {
        if ($request->status === RefundRequest::STATUS_REJECTED) {
            $this->toCustomer($this->preorderOf($request), 'refund_rejected', "refund.rejected:{$request->id}", []);

            return;
        }

        $this->toCustomer($this->preorderOf($request), 'refund_approved', "refund.approved:{$request->id}", [
            'amount' => $this->toman((int) $request->approved_amount_rial),
        ]);
    }

    public function refundExecuted(Refund $refund): void
    {
        $this->toCustomer($this->preorderOf($refund->refundRequest), 'refund_executed', "refund.executed:{$refund->id}", [
            'amount' => $this->toman($refund->amount_rial),
            'reference' => (string) $refund->reference,
        ]);
    }

    /**
     * خریدارانی که برای وضعیت جدید ایده پیامک می‌گیرند: سفارش پرداخت‌شده
     * یا بخشی بازپرداخت‌شده؛ کسی که کل مبلغش بازپرداخت شده مشتری نیست.
     */
    public function ideaStatusRecipients(Idea $idea)
    {
        return Preorder::with('contact')
            ->whereHas('landingPage', fn ($q) => $q->where('idea_id', $idea->id))
            ->whereIn('payment_status', [Preorder::PAYMENT_PAID, Preorder::PAYMENT_PARTIALLY_REFUNDED])
            ->get()
            ->unique(fn (Preorder $preorder) => $this->mobileOf($preorder))
            ->filter(fn (Preorder $preorder) => $this->mobileOf($preorder) !== null)
            ->values();
    }

    /**
     * کسانی که «وقتی آماده شد خبرم کن» را زده‌اند و خریدار نیستند (مرحله ۱۳۲)؛
     * یک ردیف برای هر شماره.
     *
     * @return \Illuminate\Support\Collection<int, Lead>
     */
    public function launchRequesters(Idea $idea)
    {
        $buyers = $this->ideaStatusRecipients($idea)->map(fn (Preorder $preorder) => $this->mobileOf($preorder))->all();

        return Lead::with(['contact', 'landingPage'])
            ->whereHas('landingPage', fn ($q) => $q->where('idea_id', $idea->id))
            ->where('intent', Lead::INTENT_NOTIFY)
            ->latest('id')
            ->get()
            ->filter(fn (Lead $lead) => ($mobile = $this->mobileOfLead($lead)) !== null && ! in_array($mobile, $buyers, true))
            ->unique(fn (Lead $lead) => $this->mobileOfLead($lead))
            ->values();
    }

    /**
     * پیامک آمادگی به درخواست‌کنندگان؛ پیام خدماتی است (خود مشتری درخواست
     * کرده) و به رضایت تبلیغاتی وابسته نیست. برای هر شماره و ایده فقط یک بار.
     *
     * @return int تعداد پیامک ثبت‌شده در صف
     */
    public function ideaLaunchedToRequesters(Idea $idea): int
    {
        $queued = 0;

        foreach ($this->launchRequesters($idea) as $lead) {
            $mobile = $this->mobileOfLead($lead);

            $message = $this->sms->queueTemplate('idea_launched_notify', $mobile, [
                'name' => $lead->contact?->name ?: $lead->name,
                'idea' => $idea->name,
                'link' => $lead->landingPage ? route('landing-pages.show', $lead->landingPage->slug) : '',
            ], "idea.launch_notify:{$idea->id}:{$mobile}", ['contact_id' => $lead->contact_id]);

            $queued += $message ? 1 : 0;
        }

        return $queued;
    }

    /**
     * @return int تعداد پیامک ثبت‌شده در صف
     */
    public function ideaStatusChanged(Idea $idea, IdeaStatusChange $change): int
    {
        $key = self::IDEA_STATUS_TEMPLATES[$change->to_status] ?? null;

        if (! $key) {
            return 0;
        }

        $queued = 0;

        foreach ($this->ideaStatusRecipients($idea) as $preorder) {
            $mobile = $this->mobileOf($preorder);

            $message = $this->sms->queueTemplate($key, $mobile, [
                'name' => $this->nameOf($preorder),
                'idea' => $idea->name,
            ], "idea.status:{$change->id}:{$mobile}", ['contact_id' => $preorder->contact_id]);

            $queued += $message ? 1 : 0;
        }

        return $queued;
    }

    private function toCustomer(?Preorder $preorder, string $template, string $dedupeKey, array $values): void
    {
        $mobile = $preorder ? $this->mobileOf($preorder) : null;

        if (! $mobile) {
            return;
        }

        $this->sms->queueTemplate($template, $mobile, $values + [
            'name' => $this->nameOf($preorder),
            'idea' => (string) $preorder->landingPage?->idea?->name,
        ], $dedupeKey, ['contact_id' => $preorder->contact_id]);
    }

    private function preorderOf(RefundRequest $request): ?Preorder
    {
        return $request->preorder()->with(['contact', 'landingPage.idea'])->first();
    }

    private function mobileOfLead(Lead $lead): ?string
    {
        return $lead->contact?->mobile ?? PhoneNumber::normalizeMobile($lead->phone);
    }

    private function mobileOf(Preorder $preorder): ?string
    {
        return $preorder->contact?->mobile ?? PhoneNumber::normalizeMobile($preorder->phone);
    }

    private function nameOf(Preorder $preorder): string
    {
        return (string) ($preorder->contact?->name ?: $preorder->name ?: 'مشتری');
    }

    private function toman(int $rial): string
    {
        return number_format(Money::rialToToman($rial));
    }
}

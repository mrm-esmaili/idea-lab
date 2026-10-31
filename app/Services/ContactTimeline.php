<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\SmsMessage;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * تاریخچه تعاملات مخاطب (مرحله ۳۹) از داده‌های موجود؛ چیزی تکراری ذخیره نمی‌شود.
 *
 * @phpstan-type Item array{at: \DateTimeInterface, icon: string, title: string, detail: ?string, url: ?string}
 */
class ContactTimeline
{
    /**
     * @return Collection<int, array>
     */
    public function for(Contact $contact): Collection
    {
        $items = collect();

        foreach ($contact->leads()->with('landingPage.idea')->get() as $lead) {
            $items->push($this->item($lead->created_at, 'bi-person-lines-fill',
                ($lead->intent === \App\Models\Lead::INTENT_NOTIFY ? '«خبرم کن»: ' : 'فرم علاقه‌مندی: ') . ($lead->landingPage?->idea?->name ?? '—'),
                trim(($lead->landingPage?->name ?? '') . ($lead->campaign ? " — کمپین {$lead->campaign}" : '')),
            ));
        }

        $preorders = $contact->preorders()->with(['landingPage.idea', 'payments'])->get();

        foreach ($preorders as $preorder) {
            $items->push($this->item($preorder->created_at, 'bi-bag',
                'درخواست پیش‌خرید: ' . ($preorder->landingPage?->idea?->name ?? '—'),
                Money::formatRialAsToman($preorder->amount_rial),
                route('admin.preorders.show', $preorder),
            ));

            foreach ($preorder->payments as $payment) {
                $items->push($this->item($payment->verified_at ?? $payment->created_at, 'bi-credit-card',
                    'پرداخت ' . config("statuses.payment.{$payment->status}", $payment->status),
                    Money::formatRialAsToman($payment->amount_rial) . ($payment->ref_id ? " — کد پیگیری {$payment->ref_id}" : ''),
                    route('admin.preorders.show', $preorder),
                ));
            }
        }

        $interests = DB::table('contact_idea_interests')
            ->join('ideas', 'ideas.id', '=', 'contact_idea_interests.idea_id')
            ->where('contact_id', $contact->id)
            ->where('contact_idea_interests.source', 'admin')
            ->get(['ideas.name', 'contact_idea_interests.created_at']);

        foreach ($interests as $interest) {
            $items->push($this->item(\Illuminate\Support\Carbon::parse($interest->created_at), 'bi-lightbulb',
                "علاقه ثبت‌شده توسط مدیر: {$interest->name}", null));
        }

        foreach (SmsMessage::where('mobile', $contact->mobile)->latest('id')->limit(50)->get() as $sms) {
            $items->push($this->item($sms->created_at, 'bi-chat-dots',
                'پیامک ' . config("statuses.sms_category.{$sms->category}", $sms->category)
                    . ($sms->status === SmsMessage::STATUS_SENT ? '' : ' (' . config("statuses.sms_message.{$sms->status}", $sms->status) . ')'),
                $sms->template_key ?? \Illuminate\Support\Str::limit((string) $sms->body, 60),
            ));
        }

        if ($contact->marketing_consent_at) {
            $items->push($this->item($contact->marketing_consent_at, 'bi-check2-circle',
                'رضایت دریافت پیامک تبلیغاتی', $contact->marketing_consent_source));
        }

        if ($contact->marketing_opted_out_at) {
            $items->push($this->item($contact->marketing_opted_out_at, 'bi-x-circle', 'لغو دریافت پیامک تبلیغاتی', null));
        }

        if ($contact->last_login_at) {
            $items->push($this->item($contact->last_login_at, 'bi-box-arrow-in-left', 'آخرین ورود به حساب', null));
        }

        return $items->sortByDesc(fn ($item) => $item['at']->getTimestamp())->values();
    }

    private function item(\DateTimeInterface $at, string $icon, string $title, ?string $detail, ?string $url = null): array
    {
        return compact('at', 'icon', 'title', 'detail', 'url');
    }
}

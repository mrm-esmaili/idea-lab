<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Rules\IranianMobile;
use App\Support\PhoneNumber;
use App\Models\LandingPageEvent;
use App\Models\Lead;
use App\Models\Preorder;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class PreorderController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],

            'phone' => [
                'required',
                'string',
                'max:30',
                new IranianMobile(),
            ],

            'consent' => ['accepted'],

            'accept_terms' => ['accepted'],
            'refund_policy_version' => ['required', 'integer'],
        ], [
            'accept_terms.accepted' => 'پذیرش شرایط بازپرداخت برای ثبت پیش‌خرید لازم است.',
        ]);

        $data['phone'] = PhoneNumber::normalizeMobile($data['phone']);

        $landingPage = LandingPage::where('slug', $slug)
            ->publiclyVisible()
            ->firstOrFail();

        // فرم غیرفعال‌شده توسط مدیر نباید از مسیر مستقیم هم قابل ارسال باشد
        abort_unless($landingPage->contentData()['show_preorder_form'], 404);

        $idea = $landingPage->idea()->with('currentRefundPolicy')->first();

        abort_unless($idea->acceptsPreorders(), 404);

        $policy = $idea->currentRefundPolicy;

        // مشتری باید همان نسخه‌ای را پذیرفته باشد که ثبت می‌شود
        if ((int) $data['refund_policy_version'] !== $policy->version) {
            throw ValidationException::withMessages([
                'accept_terms' => 'شرایط بازپرداخت به‌روزرسانی شده است؛ لطفاً شرایط جدید را مطالعه و دوباره ثبت کنید.',
            ]);
        }

        $visitorUuid = $request->cookie('visitor_uuid');

        $visitor = $visitorUuid
            ? Visitor::where('uuid', $visitorUuid)->first()
            : null;

        $latestEvent = null;

        if ($visitor) {
            $latestEvent = LandingPageEvent::where(
                'visitor_id',
                $visitor->id
            )
                ->where('landing_page_id', $landingPage->id)
                ->latest('occurred_at')
                ->first();
        }

        $existingLead = Lead::where(
            'landing_page_id',
            $landingPage->id
        )
            ->where('phone', $data['phone'])
            ->latest()
            ->first();

        $preorder = DB::transaction(function () use (
            $idea,
            $policy,
            $landingPage,
            $visitor,
            $latestEvent,
            $existingLead,
            $data
        ) {
            $preorder = Preorder::create([
                'landing_page_id' => $landingPage->id,
                'visitor_id' => $visitor?->id,
                'lead_id' => $existingLead?->id,

                'name' => $data['name'],
                'phone' => $data['phone'],

                'status' => 'requested',

                'source' => $latestEvent?->source
                    ?? $visitor?->first_source,

                'medium' => $latestEvent?->medium
                    ?? $visitor?->first_medium,

                'campaign' => $latestEvent?->campaign
                    ?? $visitor?->first_campaign,

                'consented_at' => now(),

                'public_token' => (string) Str::uuid(),

                // snapshot مبلغ و شرایط در لحظه ثبت سفارش
                'amount_rial' => $idea->preorder_amount_rial,
                'refund_policy_id' => $policy->id,
                'terms_accepted_at' => now(),
            ]);

            LandingPageEvent::create([
                'landing_page_id' => $landingPage->id,
                'visitor_id' => $visitor?->id,
                'event_name' => 'preorder_submit',

                'source' => $preorder->source,
                'medium' => $preorder->medium,
                'campaign' => $preorder->campaign,

                'occurred_at' => now(),
            ]);

            return $preorder;
        });

        // return redirect()
        //     ->route('landing-pages.show', [
        //         'slug' => $landingPage->slug,
        //     ])
        //     ->with('preorder_success', true);
        return redirect()->route(
            'preorders.checkout',
            ['token' => $preorder->public_token]
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Services\ContactActivity;
use App\Services\ContactResolver;
use App\Rules\IranianMobile;
use App\Support\PhoneNumber;
use App\Models\Visitor;
use App\Models\LandingPageEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function store(Request $request, string $slug)
    {
        $landingPage = LandingPage::query()
            ->where('slug', $slug)
            ->publiclyVisible()
            ->firstOrFail();

        // فرم غیرفعال‌شده توسط مدیر نباید از مسیر مستقیم هم قابل ارسال باشد
        abort_unless($landingPage->contentData()['show_lead_form'], 404);

        // کیسه خطای جدا تا خطای این فرم روی فرم پیش‌خرید همان صفحه نمایش داده نشود
        $data = $request->validateWithBag('lead', [
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                'max:30',
                new IranianMobile(),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'consent' => ['accepted'],
            'marketing_consent' => ['nullable', 'boolean'],
        ]);

        $data['phone'] = PhoneNumber::normalizeMobile($data['phone']);

        $visitorUuid = $request->cookie('visitor_uuid');

        $visitor = null;

        if (
            is_string($visitorUuid) &&
            Str::isUuid($visitorUuid)
        ) {
            $visitor = Visitor::where(
                'uuid',
                $visitorUuid
            )->first();
        }

        $latestEvent = null;

        if ($visitor) {
            $latestEvent = LandingPageEvent::query()
                ->where('visitor_id', $visitor->id)
                ->where('landing_page_id', $landingPage->id)
                ->latest('occurred_at')
                ->first();
        }

        $contact = app(ContactResolver::class)->forMobile($data['phone'], trim($data['name']));

        // رضایت پیامک تبلیغاتی فقط با تیک صریح ثبت می‌شود؛ تیک‌نزدن رضایت قبلی را لغو نمی‌کند
        if ($request->boolean('marketing_consent')) {
            $contact->setMarketingConsent(true, 'lead_form:' . $landingPage->slug);
        }

        $lead = DB::transaction(function () use (
            $contact,
            $data,
            $landingPage,
            $visitor,
            $latestEvent
        ) {
            $lead = $landingPage->leads()->create([
                'visitor_id' => $visitor?->id,
                'contact_id' => $contact->id,
                'name' => trim($data['name']),
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'status' => 'new',

                'source' => $latestEvent?->source
                    ?? $visitor?->first_source,

                'medium' => $latestEvent?->medium
                    ?? $visitor?->first_medium,

                'campaign' => $latestEvent?->campaign
                    ?? $visitor?->first_campaign,

                'content' => $latestEvent?->content
                    ?? $visitor?->first_content,

                'term' => $latestEvent?->term
                    ?? $visitor?->first_term,

                'consented_at' => now(),
            ]);

            LandingPageEvent::create([
                'landing_page_id' => $landingPage->id,
                'visitor_id' => $visitor?->id,
                'event_name' => 'form_submit',

                'source' => $lead->source,
                'medium' => $lead->medium,
                'campaign' => $lead->campaign,
                'content' => $lead->content,
                'term' => $lead->term,

                'occurred_at' => now(),
            ]);

            app(ContactActivity::class)->recordSubmission(
                $contact,
                $landingPage,
                'lead_form',
                $lead->source,
                $lead->medium,
                $lead->campaign,
            );

            return $lead;
        });

        return redirect()
            ->to(route('landing-pages.show', $landingPage->slug) . '#lead-form')
            ->with('lead_success', $landingPage->slug);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
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

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => [
                'required',
                'string',
                'max:30',
                new IranianMobile(),
            ],
            'email' => ['nullable', 'email', 'max:255'],
            'consent' => ['accepted'],
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

        $lead = DB::transaction(function () use (
            $data,
            $landingPage,
            $visitor,
            $latestEvent
        ) {
            $lead = $landingPage->leads()->create([
                'visitor_id' => $visitor?->id,
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

            return $lead;
        });

        return redirect()
            ->route('landing-pages.show', $landingPage->slug)
            ->with('lead_success', true);
    }
}

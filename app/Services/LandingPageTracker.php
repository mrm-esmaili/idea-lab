<?php

namespace App\Services;

use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use App\Models\Visitor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LandingPageTracker
{
    public function trackPageView(
        Request $request,
        LandingPage $landingPage
    ): void {
        $visitorUuid = $request->cookie('visitor_uuid');

        if (
            ! is_string($visitorUuid) ||
            ! Str::isUuid($visitorUuid)
        ) {
            $visitorUuid = (string) Str::uuid();
        }

        $now = now();

        $utm = [
            'source' => $this->getUtm($request, 'utm_source'),
            'medium' => $this->getUtm($request, 'utm_medium'),
            'campaign' => $this->getUtm($request, 'utm_campaign'),
            'content' => $this->getUtm($request, 'utm_content'),
            'term' => $this->getUtm($request, 'utm_term'),
        ];

        DB::transaction(function () use (
            $visitorUuid,
            $utm,
            $now,
            $request,
            $landingPage
        ) {
            $visitor = Visitor::firstOrCreate(
                ['uuid' => $visitorUuid],
                [
                    'first_source' => $utm['source'],
                    'first_medium' => $utm['medium'],
                    'first_campaign' => $utm['campaign'],
                    'first_content' => $utm['content'],
                    'first_term' => $utm['term'],
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                ]
            );

            $visitor->update([
                'last_seen_at' => $now,
            ]);

            $referrerHost = $this->getReferrerHost($request);

            LandingPageEvent::create([
                'landing_page_id' => $landingPage->id,
                'visitor_id' => $visitor->id,
                'event_name' => 'page_view',

                'source' => $utm['source'],
                'medium' => $utm['medium'],
                'campaign' => $utm['campaign'],
                'content' => $utm['content'],
                'term' => $utm['term'],

                'referrer_host' => $referrerHost,
                'occurred_at' => $now,
            ]);
        });

        cookie()->queue(
            cookie(
                'visitor_uuid',
                $visitorUuid,
                60 * 24 * 180,
                '/',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            )
        );
    }

    private function getUtm(
        Request $request,
        string $key
    ): ?string {
        $value = $request->query($key);

        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return Str::limit($value, 255, '');
    }

    private function getReferrerHost(Request $request): ?string
    {
        $referer = $request->headers->get('referer');

        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $host = parse_url($referer, PHP_URL_HOST);

        return is_string($host)
            ? Str::limit($host, 255, '')
            : null;
    }

    public function trackEvent(
        \Illuminate\Http\Request $request,
        \App\Models\LandingPage $landingPage,
        string $eventName
    ): void {
        $allowedEvents = [
            'cta_click',
            'form_view',
            'preorder_submit',
            'payment_start',
            'payment_success',
        ];

        if (!in_array($eventName, $allowedEvents, true)) {
            return;
        }

        $visitorUuid = $request->cookie('visitor_uuid');

        $visitor = is_string($visitorUuid) && Str::isUuid($visitorUuid)
            ? \App\Models\Visitor::where('uuid', $visitorUuid)->first()
            : null;

        $latestEvent = null;

        if ($visitor) {
            $latestEvent = \App\Models\LandingPageEvent::where(
                'visitor_id',
                $visitor->id
            )
                ->where('landing_page_id', $landingPage->id)
                ->latest('occurred_at')
                ->first();
        }

        $utm = [
            'source' => $this->getUtm($request, 'utm_source'),
            'medium' => $this->getUtm($request, 'utm_medium'),
            'campaign' => $this->getUtm($request, 'utm_campaign'),
            'content' => $this->getUtm($request, 'utm_content'),
            'term' => $this->getUtm($request, 'utm_term'),
        ];

        \App\Models\LandingPageEvent::create([
            'landing_page_id' => $landingPage->id,
            'visitor_id' => $visitor?->id,
            'event_name' => $eventName,

            'source' => $utm['source']
                ?? $latestEvent?->source
                ?? $visitor?->first_source,

            'medium' => $utm['medium']
                ?? $latestEvent?->medium
                ?? $visitor?->first_medium,

            'campaign' => $utm['campaign']
                ?? $latestEvent?->campaign
                ?? $visitor?->first_campaign,

            'content' => $utm['content']
                ?? $latestEvent?->content
                ?? $visitor?->first_content,

            'term' => $utm['term']
                ?? $latestEvent?->term
                ?? $visitor?->first_term,

            'referrer_host' => $this->getReferrerHost($request),

            'occurred_at' => now(),
        ]);
    }
}

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

            $referrerHost = null;
            $referer = $request->headers->get('referer');

            if ($referer) {
                $host = parse_url($referer, PHP_URL_HOST);

                if (is_string($host)) {
                    $referrerHost = Str::limit($host, 255, '');
                }
            }

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
}

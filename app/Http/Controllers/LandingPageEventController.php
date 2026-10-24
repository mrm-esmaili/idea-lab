<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Services\LandingPageTracker;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LandingPageEventController extends Controller
{
    public function store(
        Request $request,
        string $slug,
        LandingPageTracker $tracker
    ) {
        $data = $request->validate([
            'event_name' => [
                'required',
                'string',
                Rule::in([
                    'cta_click',
                    'form_view',
                    'payment_start',
                ]),
            ],
        ]);

        $landingPage = LandingPage::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $tracker->trackEvent(
            $request,
            $landingPage,
            $data['event_name']
        );

        return response()->json([
            'success' => true,
        ]);
    }
}

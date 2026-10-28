<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Services\LandingPageTracker;
use Illuminate\Http\Request;

class PublicLandingPageController extends Controller
{
    public function show(
        Request $request,
        string $slug,
        LandingPageTracker $tracker
    ) {
        $landingPage = LandingPage::query()
            ->where('slug', $slug)
            ->publiclyVisible()
            ->with('idea')
            ->firstOrFail();

        $tracker->trackPageView($request, $landingPage);

        return response()
            ->view('landing-pages.show', compact('landingPage'));
    }
}

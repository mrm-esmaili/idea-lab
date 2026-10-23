<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;

class PublicLandingPageController extends Controller
{
    public function show(string $slug)
    {
        $landingPage = LandingPage::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with('idea')
            ->firstOrFail();

        return view('landing-pages.show', compact('landingPage'));
    }
}

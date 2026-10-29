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
            ->with('idea.currentRefundPolicy')
            ->firstOrFail();

        // بازگشت به همین صفحه پس از ارسال فرم (موفق یا با خطا) بازدید جدید نیست؛
        // بازدید صفحه دیگری بلافاصله پس از ارسال، بازدید عادی است
        $isFormReturn = $request->session()->has('errors')
            || $request->session()->get('lead_success') === $landingPage->slug;

        if (! $isFormReturn) {
            $tracker->trackPageView($request, $landingPage);
        }

        return response()
            ->view('landing-pages.show', compact('landingPage'));
    }
}

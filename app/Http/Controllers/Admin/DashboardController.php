<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use App\Models\Lead;
use App\Models\Visitor;

class DashboardController extends Controller
{
    public function index()
    {
        $pageViews = LandingPageEvent::where(
            'event_name',
            'page_view'
        )->count();

        $ctaClicks = LandingPageEvent::where(
            'event_name',
            'cta_click'
        )->count();

        $ctaClickRate = $pageViews > 0
            ? round(($ctaClicks / $pageViews) * 100, 2)
            : 0;

        $leads = Lead::count();

        $visitors = Visitor::count();

        $conversionRate = $pageViews > 0
            ? round(($leads / $pageViews) * 100, 2)
            : 0;

        $landingPages = LandingPage::with('idea')
            ->withCount([
                'events as page_views_count' => function ($query) {
                    $query->where('event_name', 'page_view');
                },
                'leads as leads_count',
            ])
            ->latest()
            ->paginate(20);

        return view('admin.dashboard.index', compact(
            'pageViews',
            'leads',
            'visitors',
            'conversionRate',
            'landingPages',
            'ctaClicks',
            'ctaClickRate',
        ));
    }
}

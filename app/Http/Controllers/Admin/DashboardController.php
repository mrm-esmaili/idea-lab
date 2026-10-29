<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LandingPage;
use App\Services\FunnelStats;
use App\Services\Reports\DashboardService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardService $dashboard, FunnelStats $funnel)
    {
        $landingPages = LandingPage::with('idea')->latest()->paginate(10);

        // شاخص هر صفحه با همان تعاریف گزارش‌ها (کل دوره)
        $pageStats = $funnel->grouped($landingPages->getCollection()->pluck('id'), false);

        return view('admin.dashboard.index', [
            'attention' => $dashboard->attention($request->user()),
            'activity' => $dashboard->activity(),
            'daily' => $dashboard->daily(30),
            'ideasByStatus' => $dashboard->ideasByStatus(),
            'landingPages' => $landingPages,
            'pageStats' => $pageStats,
        ]);
    }
}

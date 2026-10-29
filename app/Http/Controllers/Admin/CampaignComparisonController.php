<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Services\ExperimentMetrics;
use Illuminate\Http\Request;

/**
 * مقایسه کمپین‌ها در یک بازه با تعاریف یکسان (مرحله ۶۹).
 */
class CampaignComparisonController extends Controller
{
    public function index(Request $request, ExperimentMetrics $metrics)
    {
        $tz = config('app.display_timezone');

        $data = $request->validate([
            'campaigns' => ['nullable', 'array', 'max:10'],
            'campaigns.*' => ['integer', 'exists:campaigns,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $fromDate = $data['from'] ?? now($tz)->subDays(29)->toDateString();
        $toDate = $data['to'] ?? now($tz)->toDateString();

        $all = Campaign::with('landingPage.idea')->orderBy('name')->get();
        $selected = $all->whereIn('id', $data['campaigns'] ?? [])->values();

        $rows = collect();
        if ($selected->isNotEmpty()) {
            [$from, $to] = $metrics->bounds($fromDate, $toDate);
            $rows = $metrics->campaignRows($selected, $from, $to);
        }

        return view('admin.campaigns.compare', [
            'campaigns' => $all,
            'selected' => $selected,
            'rows' => $rows,
            'from' => $fromDate,
            'to' => $toDate,
            'metricDefs' => ExperimentMetrics::METRICS,
        ]);
    }
}

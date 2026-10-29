<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Models\SmsTemplate;
use App\Services\Reports\ReportService;
use Illuminate\Http\Request;

/**
 * گزارش‌ها (مراحل ۷۴ تا ۷۸)؛ بازه پیش‌فرض ۳۰ روز اخیر.
 */
class ReportController extends Controller
{
    public function funnel(Request $request, ReportService $reports)
    {
        $filters = $this->filters($request, ['idea' => ['nullable', 'integer', 'exists:ideas,id']]);
        [$from, $to] = $reports->bounds($filters['from'], $filters['to']);

        return view('admin.reports.funnel', [
            'report' => $reports->funnel($filters['idea'] ?? null, $from, $to),
            'filters' => $filters,
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function ideas(Request $request, ReportService $reports)
    {
        $filters = $this->filters($request);
        [$from, $to] = $reports->bounds($filters['from'], $filters['to']);

        return view('admin.reports.ideas', [
            'rows' => $reports->ideaEconomics($from, $to),
            'filters' => $filters,
            'cacMin' => ReportService::CAC_MIN_CUSTOMERS,
        ]);
    }

    public function sources(Request $request, ReportService $reports)
    {
        $filters = $this->filters($request);
        [$from, $to] = $reports->bounds($filters['from'], $filters['to']);

        return view('admin.reports.sources', [
            'rows' => $reports->sources($from, $to),
            'filters' => $filters,
        ]);
    }

    public function contacts(Request $request, ReportService $reports)
    {
        $filters = $this->filters($request);
        [$from, $to] = $reports->bounds($filters['from'], $filters['to']);

        return view('admin.reports.contacts', [
            'report' => $reports->contacts($from, $to),
            'filters' => $filters,
        ]);
    }

    public function sms(Request $request, ReportService $reports)
    {
        $filters = $this->filters($request);
        [$from, $to] = $reports->bounds($filters['from'], $filters['to']);

        return view('admin.reports.sms', [
            'report' => $reports->sms($from, $to),
            'filters' => $filters,
            'templateNames' => SmsTemplate::pluck('name', 'key'),
        ]);
    }

    private function filters(Request $request, array $extra = []): array
    {
        $tz = config('app.display_timezone');

        $data = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ] + $extra);

        return [
            'from' => $data['from'] ?? now($tz)->subDays(29)->toDateString(),
            'to' => $data['to'] ?? now($tz)->toDateString(),
        ] + $data;
    }
}

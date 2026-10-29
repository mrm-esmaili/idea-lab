<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Services\FinanceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class FinanceReportController extends Controller
{
    public function index(Request $request, FinanceReportService $finance)
    {
        $filters = $request->validate([
            'idea' => ['nullable', 'integer', 'exists:ideas,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $tz = config('app.display_timezone');

        $report = $finance->build(
            ! empty($filters['from']) ? Carbon::parse($filters['from'], $tz) : null,
            ! empty($filters['to']) ? Carbon::parse($filters['to'], $tz) : null,
            $filters['idea'] ?? null,
        );

        return view('admin.finance.index', $report + [
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
        ]);
    }
}

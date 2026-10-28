<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Services\CampaignStatsService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CampaignController extends Controller
{
    public function index(Request $request, CampaignStatsService $stats)
    {
        $filters = $request->validate([
            'idea' => ['nullable', 'integer', 'exists:ideas,id'],
            'status' => ['nullable', Rule::in(Campaign::STATUSES)],
        ]);

        $query = Campaign::query()
            ->with('landingPage.idea')
            ->latest();

        if (! empty($filters['idea'])) {
            $query->whereHas('landingPage', fn ($page) => $page->where('idea_id', $filters['idea']));
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $campaigns = $query->paginate(30)->withQueryString();

        $report = $stats->report(
            $campaigns->getCollection()->pluck('landing_page_id')->unique(),
            $campaigns->getCollection()
        );

        $ideas = Idea::orderBy('name')->get(['id', 'name']);

        return view('admin.campaigns.index', [
            'campaigns' => $campaigns,
            'stats' => $report['campaigns'],
            'ideas' => $ideas,
        ]);
    }

    public function create(Request $request)
    {
        $campaign = new Campaign([
            'landing_page_id' => $request->integer('landing_page') ?: null,
            'status' => 'planned',
        ]);

        return view('admin.campaigns.create', [
            'campaign' => $campaign,
            'pages' => $this->pageOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $campaign = Campaign::create($this->validated($request));

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', "کمپین «{$campaign->name}» ایجاد شد. لینک رهگیری را در تبلیغ استفاده کنید.");
    }

    public function show(Campaign $campaign, CampaignStatsService $stats)
    {
        $campaign->load('landingPage.idea');

        $report = $stats->report(collect([$campaign->landing_page_id]), collect([$campaign]));
        $campaignStats = $report['campaigns'][$campaign->id];

        $recentLeads = $campaign->leads()->latest()->take(10)->get();

        return view('admin.campaigns.show', compact('campaign', 'campaignStats', 'recentLeads'));
    }

    public function edit(Campaign $campaign)
    {
        return view('admin.campaigns.edit', [
            'campaign' => $campaign,
            'pages' => $this->pageOptions(),
        ]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $campaign->update($this->validated($request, $campaign));

        return redirect()
            ->route('admin.campaigns.show', $campaign)
            ->with('success', 'کمپین به‌روزرسانی شد.');
    }

    /**
     * حذف کمپین داده‌ای را پاک نمی‌کند؛ ترافیک آن در «کد ثبت‌نشده» نمایش داده می‌شود.
     */
    public function destroy(Campaign $campaign)
    {
        $ideaId = $campaign->landingPage?->idea_id;
        $campaign->delete();

        return redirect()
            ->route('admin.campaigns.index', $ideaId ? ['idea' => $ideaId] : [])
            ->with('success', 'کمپین حذف شد. بازدیدها و سرنخ‌های ثبت‌شده باقی می‌مانند.');
    }

    private function validated(Request $request, ?Campaign $campaign = null): array
    {
        $request->merge([
            'slug' => is_string($request->input('slug')) ? mb_strtolower(trim($request->input('slug'))) : $request->input('slug'),
            'budget_toman' => Money::normalizeInput($request->input('budget_toman')),
        ]);

        $data = $request->validate([
            'landing_page_id' => ['required', 'integer', 'exists:landing_pages,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/',
                Rule::unique('campaigns', 'slug')->ignore($campaign?->id),
            ],
            'source' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'medium' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'status' => ['required', Rule::in(Campaign::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'budget_toman' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [
            'slug.regex' => 'کد کمپین فقط حروف کوچک انگلیسی، عدد، خط تیره و زیرخط باشد.',
            'source.regex' => 'منبع فقط حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط باشد.',
            'medium.regex' => 'رسانه فقط حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط باشد.',
            'ends_on.after_or_equal' => 'پایان کمپین نمی‌تواند قبل از شروع آن باشد.',
        ]);

        $data['budget_rial'] = isset($data['budget_toman'])
            ? Money::tomanToRial((int) $data['budget_toman'])
            : null;

        unset($data['budget_toman']);

        return $data;
    }

    private function pageOptions()
    {
        return LandingPage::query()
            ->with('idea:id,name')
            ->orderBy('idea_id')
            ->orderBy('name')
            ->get(['id', 'idea_id', 'name', 'slug'])
            ->groupBy(fn (LandingPage $page) => $page->idea->name);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Experiment;
use App\Models\ExperimentDecision;
use App\Models\Idea;
use App\Services\AuditLogger;
use App\Services\ExperimentMetrics;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * آزمایش‌ها و تصمیم‌ها (مراحل ۶۶ تا ۷۲).
 *
 * فرضیه، بازه و معیارها فقط پیش از شروع آزمایش قابل ویرایش‌اند تا معیار
 * پس از دیدن نتیجه تغییر نکند؛ برای تغییر، تصمیم «اصلاح» ثبت و آزمایش بعدی
 * ساخته می‌شود.
 */
class ExperimentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'idea' => ['nullable', 'integer', 'exists:ideas,id'],
            'status' => ['nullable', Rule::in(Experiment::STATUSES)],
        ]);

        $experiments = Experiment::with(['idea', 'campaigns', 'decisions' => fn ($q) => $q->latest('id')])
            ->when($filters['idea'] ?? null, fn ($q, $idea) => $q->where('idea_id', $idea))
            ->latest('starts_on')
            ->latest('id')
            ->get()
            ->when($filters['status'] ?? null, fn ($items, $status) => $items->filter(fn (Experiment $e) => $e->status() === $status))
            ->values();

        $dueReviews = ExperimentDecision::with('experiment.idea')
            ->whereNull('reviewed_at')
            ->whereNotNull('review_on')
            ->whereDate('review_on', '<=', now()->timezone(config('app.display_timezone'))->toDateString())
            ->orderBy('review_on')
            ->get();

        return view('admin.experiments.index', [
            'experiments' => $experiments,
            'dueReviews' => $dueReviews,
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
            'filters' => $filters,
        ]);
    }

    public function create(Request $request)
    {
        $source = $request->filled('from') ? Experiment::with('campaigns')->find($request->integer('from')) : null;

        $experiment = $source
            ? $source->replicate(['cancelled_at', 'created_by'])->fill([
                'title' => $source->title . ' (دور بعد)',
                'starts_on' => now()->addDay(),
                'ends_on' => now()->addDays(15),
            ])
            : new Experiment([
                'idea_id' => $request->integer('idea') ?: null,
                'starts_on' => now(),
                'ends_on' => now()->addDays(14),
                'min_sample' => 100,
                'criteria' => [
                    ['metric' => 'lead_rate', 'operator' => 'gte', 'target' => 5, 'kind' => 'success'],
                    ['metric' => 'cost_per_lead_rial', 'operator' => 'gte', 'target' => 5_000_000, 'kind' => 'stop'],
                ],
            ]);

        return view('admin.experiments.create', $this->formData($experiment, $source?->campaigns->pluck('id')->all() ?? []));
    }

    public function store(Request $request, AuditLogger $audit)
    {
        [$data, $campaignIds] = $this->validated($request);

        $experiment = Experiment::create($data + ['created_by' => $request->user()->id]);
        $experiment->campaigns()->sync($campaignIds);

        $audit->log($experiment, 'experiment.created', $data + ['campaign_ids' => $campaignIds]);

        return redirect()->route('admin.experiments.show', $experiment)->with('success', 'آزمایش ثبت شد.');
    }

    public function show(Experiment $experiment, ExperimentMetrics $metrics)
    {
        $experiment->load(['idea', 'campaigns.landingPage', 'creator', 'decisions' => fn ($q) => $q->with(['decider', 'reviewer'])->latest('id')]);

        $result = $metrics->forExperiment($experiment);

        // ارزیابی پس از تصمیم: عملکرد از تاریخ تصمیم تا امروز
        $postDecision = [];
        foreach ($experiment->decisions->whereNull('reviewed_at') as $decision) {
            [$from, $to] = $metrics->bounds($decision->created_at->copy()->timezone(config('app.display_timezone')), now()->timezone(config('app.display_timezone')));
            $postDecision[$decision->id] = $metrics->scopeTotals($experiment, $from, $to);
        }

        return view('admin.experiments.show', [
            'experiment' => $experiment,
            'status' => $experiment->status(),
            'result' => $result,
            'postDecision' => $postDecision,
            'metricDefs' => ExperimentMetrics::METRICS,
        ]);
    }

    public function edit(Experiment $experiment)
    {
        abort_unless($experiment->status() === 'planned', 403, 'فقط آزمایش شروع‌نشده قابل ویرایش است.');

        return view('admin.experiments.edit', $this->formData($experiment->load('campaigns'), $experiment->campaigns->pluck('id')->all()));
    }

    public function update(Request $request, Experiment $experiment, AuditLogger $audit)
    {
        abort_unless($experiment->status() === 'planned', 403, 'فقط آزمایش شروع‌نشده قابل ویرایش است.');

        [$data, $campaignIds] = $this->validated($request);

        $experiment->update($data);
        $experiment->campaigns()->sync($campaignIds);

        $audit->log($experiment, 'experiment.updated', $data + ['campaign_ids' => $campaignIds]);

        return redirect()->route('admin.experiments.show', $experiment)->with('success', 'آزمایش به‌روزرسانی شد.');
    }

    public function cancel(Request $request, Experiment $experiment, AuditLogger $audit)
    {
        if (in_array($experiment->status(), ['decided', 'cancelled'], true)) {
            return back()->withErrors(['experiment' => 'آزمایش تصمیم‌گرفته یا لغوشده قابل لغو نیست.']);
        }

        $experiment->forceFill(['cancelled_at' => now()])->save();
        $audit->log($experiment, 'experiment.cancelled');

        return redirect()->route('admin.experiments.show', $experiment)->with('success', 'آزمایش لغو شد.');
    }

    /**
     * ثبت تصمیم مدیر با دلیل و snapshot شاخص‌های همین لحظه (مراحل ۷۰ و ۷۱).
     */
    public function decide(Request $request, Experiment $experiment, ExperimentMetrics $metrics, AuditLogger $audit)
    {
        Gate::authorize('experiments.decide');

        if (in_array($experiment->status(), ['planned', 'cancelled'], true)) {
            return back()->withErrors(['decision' => 'برای آزمایش شروع‌نشده یا لغوشده تصمیم ثبت نمی‌شود.']);
        }

        $data = $request->validate([
            'decision' => ['required', Rule::in(array_keys(ExperimentDecision::DECISIONS))],
            'reason' => ['required', 'string', 'min:10', 'max:5000'],
            'review_on' => ['nullable', 'date', 'after:today'],
        ], [], ['decision' => 'تصمیم', 'reason' => 'دلیل', 'review_on' => 'تاریخ بازبینی']);

        $snapshot = $metrics->forExperiment($experiment) + [
            'hypothesis' => $experiment->hypothesis,
            'goal' => $experiment->goal,
            'criteria_definition' => $experiment->criteria,
            'budget_rial' => $experiment->budget_rial,
            'min_sample' => $experiment->min_sample,
        ];

        $decision = $experiment->decisions()->create([
            'decision' => $data['decision'],
            'reason' => $data['reason'],
            'snapshot' => $snapshot,
            'decided_by' => $request->user()->id,
            'review_on' => $data['review_on'] ?? null,
        ]);

        $audit->log($experiment, 'experiment.decided', ['decision_id' => $decision->id, 'decision' => $data['decision']]);

        return redirect()->route('admin.experiments.show', $experiment)->with('success', 'تصمیم ثبت شد.');
    }

    /**
     * ارزیابی پس از تصمیم: اقدام انجام‌شده و نتیجه (مرحله ۷۲).
     */
    public function review(Request $request, ExperimentDecision $decision, ExperimentMetrics $metrics, AuditLogger $audit)
    {
        Gate::authorize('experiments.decide');

        if ($decision->reviewed_at) {
            return back()->withErrors(['review' => 'این تصمیم قبلاً ارزیابی شده است.']);
        }

        $data = $request->validate([
            'action_taken' => ['required', 'string', 'max:5000'],
            'outcome' => ['required', Rule::in(array_keys(ExperimentDecision::OUTCOMES))],
            'outcome_note' => ['nullable', 'string', 'max:5000'],
        ], [], ['action_taken' => 'اقدام انجام‌شده', 'outcome' => 'نتیجه']);

        $tz = config('app.display_timezone');
        [$from, $to] = $metrics->bounds($decision->created_at->copy()->timezone($tz), now()->timezone($tz));

        $decision->forceFill($data + [
            'review_snapshot' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
                'totals' => $metrics->scopeTotals($decision->experiment, $from, $to),
            ],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ])->save();

        $audit->log($decision->experiment, 'experiment.reviewed', ['decision_id' => $decision->id, 'outcome' => $data['outcome']]);

        return redirect()->route('admin.experiments.show', $decision->experiment_id)->with('success', 'ارزیابی ثبت شد.');
    }

    private function formData(Experiment $experiment, array $selectedCampaigns): array
    {
        return [
            'experiment' => $experiment,
            'selectedCampaigns' => $selectedCampaigns,
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
            'campaigns' => Campaign::with('landingPage:id,idea_id,name')->orderBy('name')->get(),
            'metricDefs' => ExperimentMetrics::METRICS,
        ];
    }

    /**
     * @return array{0: array, 1: list<int>}
     */
    private function validated(Request $request): array
    {
        $criteria = collect($request->input('criteria', []))
            ->filter(fn ($c) => is_array($c) && filled($c['metric'] ?? null))
            ->map(fn ($c) => ['target' => Money::normalizeInput($c['target'] ?? null)] + $c)
            ->values()
            ->all();
        $request->merge([
            'criteria' => $criteria,
            'budget_toman' => Money::normalizeInput($request->input('budget_toman')),
        ]);

        $data = $request->validate([
            'idea_id' => ['required', 'integer', 'exists:ideas,id'],
            'title' => ['required', 'string', 'max:150'],
            'hypothesis' => ['required', 'string', 'max:5000'],
            'goal' => ['required', 'string', 'max:5000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'budget_toman' => ['nullable', 'integer', 'min:1', 'max:10000000000'],
            'min_sample' => ['required', 'integer', 'min:0', 'max:10000000'],
            'campaign_ids' => ['nullable', 'array'],
            'campaign_ids.*' => ['integer', 'exists:campaigns,id'],
            'criteria' => ['required', 'array', 'min:1', 'max:10'],
            'criteria.*.metric' => ['required', Rule::in(array_keys(ExperimentMetrics::METRICS))],
            'criteria.*.operator' => ['required', Rule::in(['gte', 'lte'])],
            'criteria.*.target' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'criteria.*.kind' => ['required', Rule::in(['success', 'stop'])],
        ], [
            'criteria.required' => 'دست‌کم یک معیار موفقیت یا توقف تعریف کنید.',
        ], [
            'idea_id' => 'ایده',
            'title' => 'عنوان',
            'hypothesis' => 'فرضیه',
            'goal' => 'هدف',
            'starts_on' => 'تاریخ شروع',
            'ends_on' => 'تاریخ پایان',
            'budget_toman' => 'بودجه',
            'min_sample' => 'حداقل نمونه',
            'criteria.*.target' => 'مقدار هدف',
        ]);

        if (! collect($data['criteria'])->contains('kind', 'success')) {
            throw ValidationException::withMessages(['criteria' => 'دست‌کم یک معیار موفقیت لازم است.']);
        }

        $campaignIds = array_values(array_unique(array_map('intval', $data['campaign_ids'] ?? [])));
        $foreign = Campaign::whereIn('id', $campaignIds)
            ->whereDoesntHave('landingPage', fn ($q) => $q->where('idea_id', $data['idea_id']))
            ->exists();

        if ($foreign) {
            throw ValidationException::withMessages(['campaign_ids' => 'کمپین انتخاب‌شده متعلق به این ایده نیست.']);
        }

        // مقدار هدف شاخص‌های ریالی به تومان وارد می‌شود
        $criteria = array_map(function (array $c) {
            $isRial = ExperimentMetrics::METRICS[$c['metric']][1] === 'rial';

            return [
                'metric' => $c['metric'],
                'operator' => $c['operator'],
                'target' => $isRial ? Money::tomanToRial((int) $c['target']) : (float) $c['target'],
                'kind' => $c['kind'],
            ];
        }, $data['criteria']);

        return [[
            'idea_id' => (int) $data['idea_id'],
            'title' => $data['title'],
            'hypothesis' => $data['hypothesis'],
            'goal' => $data['goal'],
            'starts_on' => Carbon::parse($data['starts_on']),
            'ends_on' => Carbon::parse($data['ends_on']),
            'budget_rial' => isset($data['budget_toman']) ? Money::tomanToRial((int) $data['budget_toman']) : null,
            'min_sample' => (int) $data['min_sample'],
            'criteria' => $criteria,
        ], $campaignIds];
    }
}

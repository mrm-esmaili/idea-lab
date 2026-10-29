<?php

namespace App\Http\Controllers;

use App\Enums\IdeaStatus;
use App\Models\Campaign;
use App\Models\Idea;
use App\Models\SmsTemplate;
use App\Services\CampaignStatsService;
use App\Services\IdeaStatsService;
use App\Services\Notifications\CustomerNotifier;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IdeaController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(IdeaStatus::class)],
            'archived' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Idea::query()
            ->withCount(['landingPages', 'leads', 'preorders'])
            ->latest();

        $request->boolean('archived')
            ? $query->archived()
            : $query->active();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['q'])) {
            $query->where('name', 'like', '%' . $filters['q'] . '%');
        }

        $ideas = $query->paginate(20)->withQueryString();

        return view('ideas.index', compact('ideas'));
    }

    public function create()
    {
        return view('ideas.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $idea = Idea::create([
            'name' => $data['name'],
            'slug' => Slug::unique(Idea::class, $data['name'], 'idea'),
            'description' => $data['description'] ?? null,
            'status' => IdeaStatus::Draft->value,
        ]);

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', 'ایده با موفقیت ایجاد شد.');
    }

    public function show(
        Idea $idea,
        IdeaStatsService $stats,
        CampaignStatsService $campaignStats,
        CustomerNotifier $notifier,
    ) {
        $idea->load([
            'landingPages' => fn ($query) => $query->latest(),
            'statusChanges.user',
            'currentRefundPolicy',
        ]);

        $pageStats = $stats->forLandingPages($idea);
        $totals = $stats->totals($pageStats);

        $pageIds = $idea->landingPages->pluck('id');

        $campaigns = Campaign::whereIn('landing_page_id', $pageIds)
            ->with('landingPage')
            ->latest()
            ->get();

        $campaignReport = $campaignStats->report($pageIds, $campaigns);

        $recentLeads = $idea->leads()
            ->with('landingPage')
            ->latest('leads.created_at')
            ->take(5)
            ->get();

        // اطلاع پیامکی تغییر وضعیت به خریداران (مرحله ۶۱)
        $statusSms = [
            'buyers' => $notifier->ideaStatusRecipients($idea)->count(),
            'templates' => SmsTemplate::whereIn('key', CustomerNotifier::IDEA_STATUS_TEMPLATES)
                ->pluck('enabled', 'key')
                ->all(),
        ];

        return view('ideas.show', compact(
            'statusSms',
            'idea',
            'pageStats',
            'totals',
            'recentLeads',
            'campaigns',
            'campaignReport',
        ));
    }

    public function edit(Idea $idea)
    {
        return view('ideas.edit', compact('idea'));
    }

    public function update(Request $request, Idea $idea)
    {
        // نامک ایده ثابت می‌ماند تا پیوندهای قبلی نشکنند
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
        ]);

        $idea->update($data);

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', 'ایده به‌روزرسانی شد.');
    }

    public function changeStatus(Request $request, Idea $idea, CustomerNotifier $notifier)
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(IdeaStatus::class)],
            'note' => ['nullable', 'string', 'max:2000'],
            'notify_customers' => ['nullable', 'boolean'],
        ]);

        $change = DB::transaction(function () use ($request, $idea, $data) {
            $locked = Idea::whereKey($idea->id)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $locked->statusEnum();
            $to = IdeaStatus::from($data['status']);

            if (! $from->canTransitionTo($to)) {
                throw ValidationException::withMessages([
                    'status' => "تغییر وضعیت از «{$from->label()}» به «{$to->label()}» مجاز نیست.",
                ]);
            }

            $locked->update(['status' => $to->value]);

            return $locked->statusChanges()->create([
                'user_id' => $request->user()->id,
                'from_status' => $from->value,
                'to_status' => $to->value,
                'note' => $data['note'] ?? null,
            ]);
        });

        $message = 'وضعیت ایده تغییر کرد.';

        if ($request->boolean('notify_customers')) {
            $key = CustomerNotifier::IDEA_STATUS_TEMPLATES[$change->to_status] ?? null;

            if (! $key || ! SmsTemplate::where('key', $key)->value('enabled')) {
                $message .= ' برای این وضعیت قالب پیامک فعالی وجود ندارد؛ پیامکی ارسال نشد.';
            } else {
                $message .= ' پیامک برای ' . number_format($notifier->ideaStatusChanged($idea->fresh(), $change)) . ' خریدار در صف ارسال قرار گرفت.';
            }
        }

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', $message);
    }

    public function archive(Idea $idea)
    {
        $idea->forceFill(['archived_at' => now()])->save();

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', 'ایده بایگانی شد و صفحات فرود آن دیگر عمومی نیستند.');
    }

    public function restore(Idea $idea)
    {
        $idea->forceFill(['archived_at' => null])->save();

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', 'ایده از بایگانی خارج شد.');
    }

    public function destroy(Idea $idea)
    {
        // حذف واقعی فقط برای ایده بدون صفحه فرود؛ در غیر این صورت داده
        // بازدید، سرنخ و پرداخت از بین می‌رود و باید بایگانی شود.
        if ($idea->landingPages()->exists()) {
            return redirect()
                ->route('ideas.show', $idea)
                ->withErrors([
                    'idea' => 'این ایده صفحه فرود دارد و قابل حذف نیست؛ به‌جای حذف آن را بایگانی کنید.',
                ]);
        }

        $idea->delete();

        return redirect()
            ->route('ideas.index')
            ->with('success', 'ایده حذف شد.');
    }
}

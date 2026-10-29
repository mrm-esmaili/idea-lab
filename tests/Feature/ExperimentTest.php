<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Expense;
use App\Models\Experiment;
use App\Models\ExperimentDecision;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use App\Models\Lead;
use App\Models\Preorder;
use App\Models\User;
use App\Models\Visitor;
use App\Services\ExperimentMetrics;
use App\Services\FunnelStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExperimentTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    private LandingPage $page;

    private Campaign $divar;

    private Campaign $insta;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');
        config(['app.display_timezone' => 'Asia/Tehran']);
        $this->travelTo(Carbon::parse('2026-11-10 09:00:00', 'UTC'));

        $this->idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'testing']);
        $this->page = $this->idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't', 'is_active' => true]);
        $this->divar = Campaign::create(['landing_page_id' => $this->page->id, 'name' => 'دیوار', 'slug' => 'divar', 'status' => 'running']);
        $this->insta = Campaign::create(['landing_page_id' => $this->page->id, 'name' => 'اینستاگرام', 'slug' => 'insta', 'status' => 'running']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => AdminRole::Manager]);
    }

    /**
     * فعالیت یک بازدیدکننده در زمان مشخص: بازدید، و در صورت نیاز سرنخ و پرداخت.
     */
    private function visit(?string $campaign, string $at, bool $lead = false, bool $paid = false): void
    {
        $time = Carbon::parse($at, 'Asia/Tehran')->utc();
        $visitor = Visitor::create(['uuid' => (string) Str::uuid()]);

        LandingPageEvent::create(['landing_page_id' => $this->page->id, 'visitor_id' => $visitor->id, 'event_name' => 'page_view', 'campaign' => $campaign, 'occurred_at' => $time]);

        if ($lead) {
            $created = Lead::create(['landing_page_id' => $this->page->id, 'visitor_id' => $visitor->id, 'name' => 'x', 'phone' => '0912' . random_int(1000000, 9999999), 'campaign' => $campaign]);
            $created->forceFill(['created_at' => $time])->save();
        }

        if ($paid) {
            $preorder = Preorder::create(['landing_page_id' => $this->page->id, 'name' => 'x', 'phone' => '09120000000', 'amount_rial' => 1_000_000, 'payment_status' => 'paid', 'campaign' => $campaign, 'public_token' => (string) Str::uuid()]);
            $preorder->forceFill(['created_at' => $time])->save();
            $preorder->payments()->create(['amount_rial' => 1_000_000, 'status' => 'paid', 'authority' => Str::random(12), 'verified_at' => $time]);
        }
    }

    private function seedTraffic(): void
    {
        // داخل بازه ۱ تا ۷ آبان‌ماه آزمایش (به وقت تهران)
        foreach (range(1, 8) as $i) {
            $this->visit('divar', '2026-11-02 10:00', lead: $i <= 4, paid: $i === 1);
        }
        foreach (range(1, 4) as $i) {
            $this->visit('insta', '2026-11-03 10:00', lead: $i === 1);
        }
        $this->visit(null, '2026-11-04 10:00', lead: true);
        // خارج از بازه: ساعت ۲۳:۵۹ روز قبل از شروع و روز بعد از پایان
        $this->visit('divar', '2026-10-31 23:59', lead: true, paid: true);
        $this->visit('divar', '2026-11-08 00:01', lead: true);

        Expense::create(['idea_id' => $this->idea->id, 'campaign_id' => $this->divar->id, 'category' => 'ads', 'cost_type' => 'variable', 'amount_rial' => 8_000_000, 'incurred_on' => '2026-11-02', 'description' => 'd']);
        Expense::create(['idea_id' => $this->idea->id, 'campaign_id' => $this->insta->id, 'category' => 'ads', 'cost_type' => 'variable', 'amount_rial' => 2_000_000, 'incurred_on' => '2026-11-03', 'description' => 'i']);
        Expense::create(['idea_id' => $this->idea->id, 'category' => 'development', 'cost_type' => 'fixed', 'amount_rial' => 5_000_000, 'incurred_on' => '2026-11-05', 'description' => 'dev']);
        Expense::create(['idea_id' => $this->idea->id, 'campaign_id' => $this->divar->id, 'category' => 'ads', 'cost_type' => 'variable', 'amount_rial' => 9_000_000, 'incurred_on' => '2026-11-09', 'description' => 'later']);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'idea_id' => $this->idea->id,
            'title' => 'آزمون دیوار و اینستاگرام',
            'hypothesis' => 'مدیران باشگاه برای نوبت‌دهی آنلاین پیش‌پرداخت می‌کنند.',
            'goal' => 'سنجش تمایل به پرداخت و هزینه جذب.',
            'starts_on' => '2026-11-01',
            'ends_on' => '2026-11-07',
            'budget_toman' => '۲,۰۰۰,۰۰۰',
            'min_sample' => 10,
            'campaign_ids' => [$this->divar->id, $this->insta->id],
            'criteria' => [
                ['kind' => 'success', 'metric' => 'lead_rate', 'operator' => 'gte', 'target' => '30'],
                ['kind' => 'success', 'metric' => 'paid_count', 'operator' => 'gte', 'target' => '1'],
                ['kind' => 'stop', 'metric' => 'cost_per_lead_rial', 'operator' => 'gte', 'target' => '۵۰۰,۰۰۰'],
                ['kind' => 'success', 'metric' => '', 'operator' => 'gte', 'target' => ''],
            ],
        ];
    }

    public function test_funnel_stats_respect_date_range(): void
    {
        $this->seedTraffic();
        $metrics = app(ExperimentMetrics::class);
        [$from, $to] = $metrics->bounds('2026-11-01', '2026-11-07');

        $all = FunnelStats::totals(app(FunnelStats::class)->grouped(collect([$this->page->id]), false));
        $range = FunnelStats::totals(app(FunnelStats::class)->grouped(collect([$this->page->id]), false, $from, $to));

        $this->assertSame([15, 8, 2], [$all['unique_visitors'], $all['leads'], $all['paid_count']]);
        $this->assertSame([13, 6, 1], [$range['unique_visitors'], $range['leads'], $range['paid_count']]);
    }

    public function test_create_validates_and_stores_definition(): void
    {
        $manager = $this->manager();
        $otherIdea = Idea::create(['name' => 'کافه‌یار', 'slug' => 'cafe', 'status' => 'draft']);
        $foreign = Campaign::create(['landing_page_id' => $otherIdea->landingPages()->create(['name' => 'c', 'slug' => 'c', 'title' => 't'])->id, 'name' => 'x', 'slug' => 'x', 'status' => 'running']);

        $this->actingAs($manager)->post(route('admin.experiments.store'), $this->payload(['criteria' => []]))->assertSessionHasErrors('criteria');
        $this->actingAs($manager)->post(route('admin.experiments.store'), $this->payload(['criteria' => [['kind' => 'stop', 'metric' => 'leads', 'operator' => 'lte', 'target' => 1]]]))->assertSessionHasErrors('criteria');
        $this->actingAs($manager)->post(route('admin.experiments.store'), $this->payload(['campaign_ids' => [$foreign->id]]))->assertSessionHasErrors('campaign_ids');
        $this->actingAs($manager)->post(route('admin.experiments.store'), $this->payload(['ends_on' => '2026-10-01']))->assertSessionHasErrors('ends_on');
        $this->assertSame(0, Experiment::count());

        $this->actingAs($manager)->post(route('admin.experiments.store'), $this->payload())->assertRedirect();

        $experiment = Experiment::sole();
        $this->assertSame(20_000_000, $experiment->budget_rial);
        $this->assertCount(3, $experiment->criteria);
        $this->assertSame(5_000_000, $experiment->criteria[2]['target']);
        $this->assertEquals(30.0, $experiment->criteria[0]['target']);
        $this->assertEqualsCanonicalizing([$this->divar->id, $this->insta->id], $experiment->campaigns->pluck('id')->all());
        $this->assertSame(1, AuditLog::where('action', 'experiment.created')->count());

        $this->actingAs(User::factory()->create(['role' => AdminRole::Support]))->get(route('admin.experiments.index'))->assertForbidden();
    }

    public function test_results_use_only_in_range_data_and_evaluate_criteria(): void
    {
        $this->seedTraffic();
        $this->actingAs($this->manager())->post(route('admin.experiments.store'), $this->payload());
        $experiment = Experiment::sole();

        $result = app(ExperimentMetrics::class)->forExperiment($experiment);

        $divar = $result['campaigns'][$this->divar->id];
        $this->assertSame([8, 4, 1, 8_000_000, 2_000_000, 8_000_000], [$divar['unique_visitors'], $divar['leads'], $divar['paid_count'], $divar['spent_rial'], $divar['cost_per_lead_rial'], $divar['cost_per_paid_rial']]);
        $this->assertEquals(50.0, $divar['lead_rate']);
        $insta = $result['campaigns'][$this->insta->id];
        $this->assertSame([4, 1, 0, 2_000_000, null], [$insta['unique_visitors'], $insta['leads'], $insta['paid_count'], $insta['spent_rial'], $insta['cost_per_paid_rial']]);

        // دامنه کمپینی: بازدید بدون کمپین و هزینه توسعه شمرده نمی‌شود
        $totals = $result['totals'];
        $this->assertSame([12, 5, 1, 10_000_000, 2_000_000], [$totals['unique_visitors'], $totals['leads'], $totals['paid_count'], $totals['spent_rial'], $totals['cost_per_lead_rial']]);

        $evaluation = $result['evaluation'];
        $this->assertTrue($evaluation['sample_ok']);
        $this->assertTrue($evaluation['success_met']); // 41.67% ≥ 30 و ۱ پیش‌پرداخت
        $this->assertFalse($evaluation['stop_triggered']); // 200,000 تومان < 500,000
        $this->assertFalse($evaluation['budget_exceeded']);

        // دامنه کل ایده
        $experiment->campaigns()->detach();
        $ideaTotals = app(ExperimentMetrics::class)->forExperiment($experiment->fresh())['totals'];
        $this->assertSame([13, 6, 15_000_000], [$ideaTotals['unique_visitors'], $ideaTotals['leads'], $ideaTotals['spent_rial']]);

        $this->actingAs($this->manager())->get(route('admin.experiments.show', $experiment))
            ->assertOk()
            ->assertSee('همه معیارهای موفقیت برقرار است')
            ->assertSee('1,500,000 تومان');
    }

    public function test_status_lifecycle_and_edit_lock(): void
    {
        $manager = $this->manager();
        $this->actingAs($manager)->post(route('admin.experiments.store'), $this->payload(['starts_on' => '2026-11-20', 'ends_on' => '2026-11-30']));
        $experiment = Experiment::sole();

        $this->assertSame('planned', $experiment->status());
        $this->actingAs($manager)->get(route('admin.experiments.edit', $experiment))->assertOk();
        $this->actingAs($manager)->post(route('admin.experiments.decide', $experiment), ['decision' => 'continue', 'reason' => 'هنوز شروع نشده است.'])->assertSessionHasErrors('decision');

        $this->travelTo(Carbon::parse('2026-11-21 09:00:00', 'UTC'));
        $this->assertSame('running', $experiment->status());
        $this->actingAs($manager)->get(route('admin.experiments.edit', $experiment))->assertForbidden();
        $this->actingAs($manager)->put(route('admin.experiments.update', $experiment), $this->payload())->assertForbidden();

        $this->travelTo(Carbon::parse('2026-12-01 09:00:00', 'UTC'));
        $this->assertSame('ended', $experiment->status());

        $this->actingAs($manager)->post(route('admin.experiments.cancel', $experiment));
        $this->assertSame('cancelled', $experiment->fresh()->status());
    }

    public function test_decision_snapshot_and_post_decision_review(): void
    {
        $this->seedTraffic();
        $manager = $this->manager();
        $this->actingAs($manager)->post(route('admin.experiments.store'), $this->payload());
        $experiment = Experiment::sole();

        $this->actingAs($manager)->post(route('admin.experiments.decide', $experiment), ['decision' => 'continue', 'reason' => 'کوتاه'])->assertSessionHasErrors('reason');

        $this->actingAs($manager)->post(route('admin.experiments.decide', $experiment), [
            'decision' => 'execute',
            'reason' => 'نرخ سرنخ دیوار ۵۰٪ و یک پیش‌پرداخت؛ تمرکز بودجه روی دیوار.',
            'review_on' => '2026-11-20',
        ])->assertSessionHas('success');

        $decision = ExperimentDecision::sole();
        $this->assertSame('decided', $experiment->status());
        $this->assertSame($manager->id, $decision->decided_by);
        $this->assertSame(5, $decision->snapshot['totals']['leads']);
        $this->assertTrue($decision->snapshot['evaluation']['success_met']);
        $this->assertSame($experiment->hypothesis, $decision->snapshot['hypothesis']);

        // داده جدید snapshot را تغییر نمی‌دهد ولی در ارزیابی پس از تصمیم دیده می‌شود
        $this->travelTo(Carbon::parse('2026-11-21 09:00:00', 'UTC'));
        $this->visit('divar', '2026-11-15 10:00', lead: true, paid: true);
        $this->assertSame(5, $decision->fresh()->snapshot['totals']['leads']);
        $this->assertTrue($decision->fresh()->isReviewDue());

        $this->actingAs($manager)->get(route('admin.experiments.index'))->assertOk()->assertSee('بازبینی‌های سررسیده');

        $this->actingAs($manager)->post(route('admin.experiments.review', $decision), [
            'action_taken' => 'بودجه اینستاگرام قطع و به دیوار منتقل شد.',
            'outcome' => 'as_expected',
        ])->assertSessionHas('success');

        $decision->refresh();
        $this->assertSame('as_expected', $decision->outcome);
        // از تاریخ تصمیم (۱۰ آبان) تا امروز: سرنخ ۱۵ آبان و هزینه ۹ آبان خارج است
        $this->assertSame([1, 1, 0], [$decision->review_snapshot['totals']['leads'], $decision->review_snapshot['totals']['paid_count'], $decision->review_snapshot['totals']['spent_rial']]);
        $this->assertFalse($decision->isReviewDue());

        $this->actingAs($manager)->post(route('admin.experiments.review', $decision), ['action_taken' => 'x', 'outcome' => 'worse'])->assertSessionHasErrors('review');
        $this->assertSame(2, AuditLog::whereIn('action', ['experiment.decided', 'experiment.reviewed'])->count());
    }

    public function test_campaign_comparison_uses_same_definitions(): void
    {
        $this->seedTraffic();

        $this->actingAs($this->manager())
            ->get(route('admin.campaigns.compare', ['campaigns' => [$this->divar->id, $this->insta->id], 'from' => '2026-11-01', 'to' => '2026-11-07']))
            ->assertOk()
            ->assertSeeInOrder(['هزینه', '800,000 تومان', '200,000 تومان'])
            ->assertSeeInOrder(['نرخ سرنخ', '50.00%', '25.00%'])
            ->assertSeeInOrder(['هزینه هر سرنخ', '200,000 تومان', '200,000 تومان'])
            ->assertSee('تعریف شاخص‌ها');

        $this->actingAs($this->manager())->get(route('admin.campaigns.compare'))->assertOk()->assertSee('کمپین‌های مورد نظر را انتخاب کنید');
    }
}

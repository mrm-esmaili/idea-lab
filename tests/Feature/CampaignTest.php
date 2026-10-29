<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Campaign;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use App\Models\Preorder;
use App\Models\User;
use App\Models\Visitor;
use App\Services\CampaignStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTest extends TestCase
{
    use RefreshDatabase;

    private LandingPage $pageA;

    private LandingPage $pageB;

    protected function setUp(): void
    {
        parent::setUp();

        $idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'testing']);

        $this->pageA = $idea->landingPages()->create(['name' => 'A', 'slug' => 'page-a', 'title' => 't', 'is_active' => true]);
        $this->pageB = $idea->landingPages()->create(['name' => 'B', 'slug' => 'page-b', 'title' => 't', 'is_active' => true]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => AdminRole::Manager]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'landing_page_id' => $this->pageA->id,
            'name' => 'دیوار آبان',
            'slug' => 'divar-aban',
            'source' => 'divar',
            'medium' => 'paid',
            'status' => 'running',
            'budget_toman' => '۲,۰۰۰,۰۰۰',
        ];
    }

    private function event(LandingPage $page, ?string $campaign, string $name = 'page_view'): void
    {
        LandingPageEvent::create([
            'landing_page_id' => $page->id,
            'visitor_id' => Visitor::create(['uuid' => fake()->uuid()])->id,
            'event_name' => $name,
            'campaign' => $campaign,
            'occurred_at' => now(),
        ]);
    }

    public function test_create_normalizes_code_and_budget(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.campaigns.store'), $this->payload(['slug' => ' Divar-Aban ']))
            ->assertRedirect();

        $campaign = Campaign::sole();
        $this->assertSame('divar-aban', $campaign->slug);
        $this->assertSame(20_000_000, $campaign->budget_rial);
        $this->assertSame(
            route('landing-pages.show', 'page-a') . '?utm_source=divar&utm_medium=paid&utm_campaign=divar-aban',
            $campaign->trackingUrl()
        );

        $this->get(route('admin.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('utm_campaign=divar-aban', false)
            ->assertSee('2,000,000 تومان');
    }

    public function test_invalid_campaign_input_is_rejected(): void
    {
        Campaign::create($this->payload(['budget_toman' => null]) + ['budget_rial' => null]);

        $this->actingAs($this->manager())
            ->post(route('admin.campaigns.store'), $this->payload([
                'slug' => 'divar-aban',
                'source' => 'دیوار',
                'starts_on' => '2026-11-10',
                'ends_on' => '2026-11-01',
            ]))
            ->assertSessionHasErrors(['slug', 'source', 'ends_on']);

        $this->post(route('admin.campaigns.store'), $this->payload(['slug' => 'bad code']))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, Campaign::count());
    }

    public function test_attribution_matches_page_and_code_case_insensitively(): void
    {
        $campaign = Campaign::create([
            'landing_page_id' => $this->pageA->id,
            'name' => 'c',
            'slug' => 'divar-aban',
            'status' => 'running',
        ]);

        $this->event($this->pageA, 'divar-aban');
        $this->event($this->pageA, 'Divar-Aban');
        $this->event($this->pageA, 'divar-aban', 'cta_click');
        $this->event($this->pageB, 'divar-aban');   // صفحه دیگر: متعلق به کمپین نیست
        $this->event($this->pageA, null);           // بدون کمپین
        $this->event($this->pageA, 'typo-code');    // کد ثبت‌نشده

        $this->pageA->leads()->create(['name' => 'l', 'phone' => '09120000000', 'campaign' => 'divar-aban']);
        $preorder = Preorder::create(['landing_page_id' => $this->pageA->id, 'name' => 'p', 'phone' => '09120000000', 'campaign' => 'divar-aban', 'payment_status' => 'paid']);
        $preorder->payments()->create(['amount_rial' => 500_000, 'status' => 'paid']);

        $report = app(CampaignStatsService::class)->report(
            collect([$this->pageA->id, $this->pageB->id]),
            collect([$campaign])
        );

        $stats = $report['campaigns'][$campaign->id];
        $this->assertSame(2, $stats['page_views']);
        $this->assertSame(1, $stats['cta_clicks']);
        $this->assertSame(1, $stats['leads']);
        $this->assertSame(1, $stats['preorders']);
        $this->assertSame(1, $stats['paid_count']);
        $this->assertSame(500_000, $stats['paid_rial']);

        $other = $report['other']->map(fn ($row) => $row['landing_page_id'] . ':' . ($row['campaign'] ?? '-'))->sort()->values()->all();
        $this->assertSame([
            $this->pageA->id . ':-',
            $this->pageA->id . ':typo-code',
            $this->pageB->id . ':divar-aban',
        ], $other);
    }

    public function test_end_to_end_attribution_from_utm_visit_to_lead(): void
    {
        $campaign = Campaign::create([
            'landing_page_id' => $this->pageA->id,
            'name' => 'c',
            'slug' => 'insta-1',
            'status' => 'running',
        ]);

        $visit = $this->get('/l/page-a?utm_source=instagram&utm_campaign=insta-1');
        $cookie = $visit->getCookie('visitor_uuid', false);

        $this->withUnencryptedCookie('visitor_uuid', $cookie->getValue())
            ->post(route('leads.store', 'page-a'), [
                'name' => 'مخاطب',
                'phone' => '09121111111',
                'consent' => '1',
            ])->assertRedirect();

        $this->actingAs($this->manager())
            ->get(route('admin.campaigns.show', $campaign))
            ->assertOk()
            ->assertSee('مخاطب')
            ->assertViewHas('campaignStats', fn ($stats) => $stats['leads'] === 1 && $stats['unique_visitors'] === 1);
    }

    public function test_idea_page_shows_campaigns_and_unregistered_codes(): void
    {
        Campaign::create(['landing_page_id' => $this->pageA->id, 'name' => 'کمپین ثبت‌شده', 'slug' => 'known', 'status' => 'running']);
        $this->event($this->pageA, 'known');
        $this->event($this->pageA, 'mystery-code');

        $this->actingAs($this->manager())
            ->get(route('ideas.show', $this->pageA->idea_id))
            ->assertOk()
            ->assertSee('کمپین ثبت‌شده')
            ->assertSee('mystery-code')
            ->assertSee('کد ثبت‌نشده');
    }

    public function test_deleting_campaign_keeps_tracked_data(): void
    {
        $campaign = Campaign::create(['landing_page_id' => $this->pageA->id, 'name' => 'c', 'slug' => 'gone', 'status' => 'ended']);
        $this->event($this->pageA, 'gone');

        $this->actingAs($this->manager())
            ->delete(route('admin.campaigns.destroy', $campaign))
            ->assertRedirect();

        $this->assertModelMissing($campaign);
        $this->assertSame(1, LandingPageEvent::where('campaign', 'gone')->count());
    }

    public function test_campaigns_require_ideas_permission(): void
    {
        $this->actingAs(User::factory()->create(['role' => AdminRole::Finance]))
            ->get(route('admin.campaigns.index'))
            ->assertForbidden();

        $this->actingAs($this->manager())
            ->get(route('admin.campaigns.index'))
            ->assertOk();
    }
}

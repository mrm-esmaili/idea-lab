<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\LandingPageEvent;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\User;
use App\Models\Visitor;
use App\Services\IdeaStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdeaStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_per_landing_page_and_totals(): void
    {
        $idea = Idea::create(['name' => 'i', 'slug' => 'i', 'status' => 'testing']);
        $other = Idea::create(['name' => 'o', 'slug' => 'o', 'status' => 'testing']);

        $a = $idea->landingPages()->create(['name' => 'A', 'slug' => 'a', 'title' => 't']);
        $b = $idea->landingPages()->create(['name' => 'B', 'slug' => 'b', 'title' => 't']);
        $foreign = $other->landingPages()->create(['name' => 'F', 'slug' => 'f', 'title' => 't']);

        $v1 = Visitor::create(['uuid' => fake()->uuid()]);
        $v2 = Visitor::create(['uuid' => fake()->uuid()]);

        foreach ([[$a, $v1, 'page_view'], [$a, $v1, 'page_view'], [$a, $v2, 'page_view'], [$a, $v1, 'cta_click'], [$foreign, $v1, 'page_view']] as [$page, $visitor, $event]) {
            LandingPageEvent::create([
                'landing_page_id' => $page->id,
                'visitor_id' => $visitor->id,
                'event_name' => $event,
                'occurred_at' => now(),
            ]);
        }

        $a->leads()->create(['name' => 'l', 'phone' => '09120000000']);

        $preorder = Preorder::create(['landing_page_id' => $a->id, 'name' => 'p', 'phone' => '09120000000']);
        Preorder::create(['landing_page_id' => $b->id, 'name' => 'p', 'phone' => '09120000001']);

        Payment::create(['preorder_id' => $preorder->id, 'amount_rial' => 100000, 'status' => 'paid']);
        Payment::create(['preorder_id' => $preorder->id, 'amount_rial' => 100000, 'status' => 'failed']);

        $service = app(IdeaStatsService::class);
        $stats = $service->forLandingPages($idea);

        $this->assertSame(3, $stats[$a->id]['page_views']);
        $this->assertSame(2, $stats[$a->id]['unique_visitors']);
        $this->assertSame(1, $stats[$a->id]['cta_clicks']);
        $this->assertSame(1, $stats[$a->id]['leads']);
        $this->assertSame(50.0, $stats[$a->id]['lead_rate']);
        $this->assertSame(1, $stats[$a->id]['paid_count']);
        $this->assertSame(100000, $stats[$a->id]['paid_rial']);
        $this->assertSame(0, $stats[$b->id]['page_views']);
        $this->assertSame(1, $stats[$b->id]['preorders']);
        $this->assertFalse($stats->has($foreign->id));

        $totals = $service->totals($stats);
        $this->assertSame(2, $totals['preorders']);
        $this->assertSame(100000, $totals['paid_rial']);

        $this->actingAs(User::factory()->create(['role' => AdminRole::Manager]))
            ->get(route('ideas.show', $idea))
            ->assertOk()
            ->assertSee('10,000 تومان');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\Lead;
use App\Models\Preorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormPhoneTest extends TestCase
{
    use RefreshDatabase;

    private function landingPage(): LandingPage
    {
        $idea = Idea::create([
            'name' => 'Test idea',
            'slug' => 'test-idea',
            'status' => 'draft',
        ]);

        return $idea->landingPages()->create([
            'name' => 'Test page',
            'slug' => 'test',
            'title' => 'Test',
            'is_active' => true,
        ]);
    }

    public function test_lead_phone_is_stored_normalized(): void
    {
        $page = $this->landingPage();

        $this->post(route('leads.store', $page->slug), [
            'name' => 'علی',
            'phone' => '۰۹۱۲ ۳۴۵ ۶۷۸۹',
            'consent' => '1',
        ])->assertRedirect();

        $this->assertSame('09123456789', Lead::sole()->phone);
    }

    public function test_invalid_lead_phone_is_rejected(): void
    {
        $page = $this->landingPage();

        $this->post(route('leads.store', $page->slug), [
            'name' => 'علی',
            'phone' => '12345',
            'consent' => '1',
        ])->assertSessionHasErrors('phone');

        $this->assertSame(0, Lead::count());
    }

    public function test_preorder_links_to_lead_with_differently_formatted_phone(): void
    {
        $page = $this->landingPage();

        $this->post(route('leads.store', $page->slug), [
            'name' => 'علی',
            'phone' => '+98 912 345 6789',
            'consent' => '1',
        ]);

        $this->post(route('preorders.store', $page->slug), [
            'name' => 'علی',
            'phone' => '09123456789',
            'consent' => '1',
        ])->assertRedirect();

        $preorder = Preorder::sole();

        $this->assertSame('09123456789', $preorder->phone);
        $this->assertSame(Lead::sole()->id, $preorder->lead_id);
    }

    public function test_long_utm_values_do_not_break_event_tracking(): void
    {
        $page = $this->landingPage();

        $this->postJson(
            route('landing-pages.events.store', $page->slug)
                . '?utm_source=' . str_repeat('a', 400),
            ['event_name' => 'cta_click']
        )->assertOk();

        $this->assertSame(
            255,
            mb_strlen($page->events()->sole()->source)
        );
    }
}

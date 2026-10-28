<?php

namespace Tests\Feature;

use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLandingFormsTest extends TestCase
{
    use RefreshDatabase;

    private LandingPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');

        $idea = Idea::create([
            'name' => 'باشگاه‌یار',
            'slug' => 'clubyar',
            'status' => 'testing',
            'preorder_amount_rial' => 1_000_000,
        ]);

        $idea->refundPolicies()->create(['version' => 1, 'is_refundable' => false, 'terms' => 'غیرقابل بازپرداخت']);

        $this->page = $idea->landingPages()->create([
            'name' => 'صفحه',
            'slug' => 'clubyar',
            'title' => 'عنوان',
            'is_active' => true,
        ]);
    }

    public function test_preorder_errors_and_old_input_stay_on_preorder_form(): void
    {
        $this->from('/l/clubyar')
            ->post(route('preorders.store', 'clubyar'), [
                'form' => 'preorder',
                'name' => 'خریدار',
                'phone' => '123',
                'consent' => '1',
                'accept_terms' => '1',
                'refund_policy_version' => 1,
            ])
            ->assertSessionHasErrorsIn('preorder', 'phone');

        $html = $this->get('/l/clubyar')->assertOk()->getContent();

        [$leadPart, $preorderPart] = explode('id="preorder-form"', $html, 2);

        $this->assertStringContainsString('شماره موبایل معتبر نیست', $preorderPart);
        $this->assertStringContainsString('value="خریدار"', $preorderPart);
        $this->assertStringNotContainsString('شماره موبایل معتبر نیست', $leadPart);
        $this->assertStringNotContainsString('value="خریدار"', $leadPart);
    }

    public function test_validation_messages_are_persian(): void
    {
        $this->from('/l/clubyar')
            ->post(route('leads.store', 'clubyar'), ['form' => 'lead', 'name' => '', 'phone' => '09121111111'])
            ->assertSessionHasErrorsIn('lead', 'name');

        $this->get('/l/clubyar')
            ->assertSee('نام الزامی است.')
            ->assertSee('پذیرفتن رضایت تماس الزامی است.');
    }

    public function test_successful_lead_returns_to_form_without_counting_a_view(): void
    {
        $this->get('/l/clubyar');
        $this->assertSame(1, LandingPageEvent::where('event_name', 'page_view')->count());

        $this->post(route('leads.store', 'clubyar'), [
            'form' => 'lead',
            'name' => 'مخاطب',
            'phone' => '09121111111',
            'consent' => '1',
        ])->assertRedirect(route('landing-pages.show', 'clubyar') . '#lead-form');

        $this->get('/l/clubyar')->assertSee('اطلاعات شما با موفقیت ثبت شد');
        $this->assertSame(1, LandingPageEvent::where('event_name', 'page_view')->count());

        // بازدید بعدی عادی دوباره شمرده می‌شود
        $this->get('/l/clubyar');
        $this->assertSame(2, LandingPageEvent::where('event_name', 'page_view')->count());
    }

    public function test_privacy_link_only_when_configured(): void
    {
        $this->get('/l/clubyar')->assertDontSee('/privacy', false);

        config(['app.privacy_url' => 'https://example.com/privacy']);

        $this->get('/l/clubyar')->assertSee('https://example.com/privacy', false);
    }
}

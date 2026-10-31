<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\Lead;
use App\Models\User;
use App\Services\ContactSegment;
use App\Services\ExperimentMetrics;
use App\Services\FunnelStats;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * چیدمان جدید صفحه و جریان «فقط خبرم کن» (مراحل ۱۲۹ تا ۱۳۶).
 */
class NotifyMeFlowTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    private LandingPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.public_form_min_seconds' => 0]);

        $this->idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'testing', 'preorder_amount_rial' => 1_200_000]);
        $this->page = $this->idea->landingPages()->create([
            'name' => 'p', 'slug' => 'club', 'title' => 'عنوان', 'is_active' => true,
            'content' => ['faq' => [['q' => 'کی؟', 'a' => 'به‌زودی']], 'features' => [['title' => 'ویژگی', 'description' => null]]],
        ]);
    }

    private function openPreorders(): void
    {
        $this->idea->refundPolicies()->create(['version' => 1, 'is_refundable' => true, 'refund_window_days' => 14, 'terms' => 't']);
    }

    private function submit(array $data = [])
    {
        return $this->post(route('leads.store', 'club'), $data + [
            'form' => 'lead', 'name' => 'سارا', 'phone' => '09121112233', 'consent' => '1',
            'intent' => Lead::INTENT_NOTIFY,
            '_form_started' => Crypt::encryptString((string) (now()->getTimestamp() - 10)),
        ]);
    }

    public function test_page_order_puts_preorder_before_faq_and_notify_form(): void
    {
        $this->openPreorders();

        $html = $this->get('/l/club')->assertOk()->getContent();

        $positions = array_map(fn ($needle) => strpos($html, $needle), [
            'id="main-cta"', '<h3>ویژگی</h3>', 'id="preorder-form"', '<summary>کی؟</summary>', 'id="lead-form"',
        ]);
        $this->assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions, 'ترتیب: دکمه‌ها، ویژگی‌ها، پیش‌خرید، پرسش‌ها، خبرم کن');

        $this->assertStringContainsString('<a href="#preorder-form" class="cta" id="main-cta" data-track="cta_click">پیش‌خرید</a>', $html);
        $this->assertStringContainsString('<a href="#lead-form" class="cta cta-secondary" data-track="notify_click">وقتی آماده شد خبرم کن</a>', $html);
        $this->assertStringContainsString('فعلاً پیش‌پرداخت نمی‌کنم؛ وقتی آماده شد خبرم کن', $html);
        $this->assertStringContainsString('name="intent" value="notify_when_ready"', $html);
        $this->assertStringNotContainsString('name="email"', $html);
    }

    public function test_closed_preorder_makes_notify_the_main_action(): void
    {
        $this->get('/l/club')
            ->assertSee('<a href="#lead-form" class="cta" id="main-cta" data-track="cta_click">خبرم کن</a>', false)
            ->assertDontSee('id="preorder-form"', false)
            ->assertDontSee('فعلاً پیش‌پرداخت نمی‌کنم');
    }

    public function test_notify_request_is_stored_with_intent(): void
    {
        $this->submit()->assertSessionHasNoErrors('lead');
        $this->assertSame(Lead::INTENT_NOTIFY, Lead::sole()->intent);

        $this->get('/l/club')->assertSee('با پیامک خبرتان می‌کنیم');

        // فرم ذخیره‌شده پیش از نسخه ۱.۱ (بدون intent) ثبت علاقه است
        $this->travel(61)->seconds();
        $this->submit(['intent' => null, 'phone' => '09121112244'])->assertSessionHasNoErrors('lead');
        $this->assertSame(Lead::INTENT_INTEREST, Lead::latest('id')->first()->intent);

        $this->submit(['intent' => 'buy_now', 'phone' => '09121112255'])->assertSessionHasErrors('intent', null, 'lead');

        $this->postJson(route('landing-pages.events.store', 'club'), ['event_name' => 'notify_click'])->assertOk();
    }

    public function test_admin_sees_and_segments_notify_requests(): void
    {
        $this->submit();
        $this->travel(61)->seconds();
        $this->submit(['intent' => Lead::INTENT_INTEREST, 'phone' => '09121112244', 'name' => 'رضا']);

        $manager = User::factory()->create(['role' => AdminRole::Manager]);

        $this->actingAs($manager)->get(route('admin.leads.index', ['intent' => Lead::INTENT_NOTIFY]))
            ->assertOk()->assertSee('سارا')->assertDontSee('رضا');

        $segment = app(ContactSegment::class);
        $this->assertSame(['09121112233'], $segment->apply(Contact::query(), ['notify_idea' => $this->idea->id])->pluck('mobile')->all());
        $this->assertContains('«خبرم کن» برای: باشگاه‌یار', $segment->describe(['notify_idea' => $this->idea->id], [$this->idea->id => 'باشگاه‌یار']));

        $this->actingAs($manager)->post(route('admin.groups.store'), [
            'name' => 'منتظران باشگاه‌یار', 'type' => 'dynamic', 'rules' => ['notify_idea' => $this->idea->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame(['notify_idea' => (string) $this->idea->id], array_map('strval', ContactGroup::sole()->rules));

        $stats = app(FunnelStats::class)->grouped(collect([$this->page->id]), false)->first();
        $this->assertSame(2, $stats['leads']);
        $this->assertSame(1, $stats['notify_requests']);
        $this->assertArrayHasKey('notify_rate', ExperimentMetrics::METRICS);

        $this->actingAs($manager)->get(route('ideas.show', $this->idea))->assertSee('1 درخواست «خبرم کن»');
        $this->actingAs($manager)->get(route('admin.reports.funnel'))->assertOk()->assertSee('درخواست «خبرم کن»');
    }
}

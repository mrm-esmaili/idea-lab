<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Expense;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use App\Models\Lead;
use App\Models\Preorder;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Reports\ReportService;
use App\Support\Format;
use App\Support\Jalali;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    private LandingPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');
        config(['app.display_timezone' => 'Asia/Tehran']);
        $this->travelTo(Carbon::parse('2026-10-29 09:00:00', 'UTC'));

        $this->idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'launched']);
        $this->page = $this->idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't', 'is_active' => true]);
    }

    private function user(AdminRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function visit(?string $source, bool $lead = false, bool $paid = false, string $daysAgo = '1'): void
    {
        $time = now()->subDays((int) $daysAgo);
        $visitor = Visitor::create(['uuid' => (string) Str::uuid()]);

        LandingPageEvent::create(['landing_page_id' => $this->page->id, 'visitor_id' => $visitor->id, 'event_name' => 'page_view', 'source' => $source, 'occurred_at' => $time]);

        if ($lead) {
            Lead::create(['landing_page_id' => $this->page->id, 'visitor_id' => $visitor->id, 'name' => 'x', 'phone' => '0912' . random_int(1000000, 9999999), 'source' => $source])
                ->forceFill(['created_at' => $time])->save();
        }

        if ($paid) {
            $preorder = Preorder::create(['landing_page_id' => $this->page->id, 'name' => 'x', 'phone' => '09120000000', 'amount_rial' => 1_000_000, 'payment_status' => 'paid', 'source' => $source, 'public_token' => (string) Str::uuid()]);
            $preorder->forceFill(['created_at' => $time])->save();
            $preorder->payments()->create(['amount_rial' => 1_000_000, 'status' => 'paid', 'authority' => Str::random(12), 'verified_at' => $time]);
        }
    }

    public function test_jalali_conversion_and_display_timezone(): void
    {
        $this->assertSame([1405, 8, 7], Jalali::fromGregorian(2026, 10, 29));
        $this->assertSame([1403, 12, 30], Jalali::fromGregorian(2025, 3, 20));
        $this->assertSame([1404, 1, 1], Jalali::fromGregorian(2025, 3, 21));
        $this->assertSame([2026, 3, 21], Jalali::toGregorian(1405, 1, 1));

        // ۲۱:۰۰ UTC = ۰۰:۳۰ روز بعد در تهران
        $this->assertSame('1405/08/08 00:30', Format::datetime(Carbon::parse('2026-10-29 21:00:00', 'UTC')));
        $this->assertSame('1405/08/07', Format::date(Carbon::parse('2026-10-29')));
        $this->assertSame('7 آبان 1405', Jalali::format(Carbon::parse('2026-10-29 09:00', 'UTC'), 'j F Y'));
        $this->assertSame('—', Format::datetime(null));
    }

    public function test_dashboard_shows_attention_items_by_permission_and_weekly_activity(): void
    {
        $this->visit('divar', lead: true, paid: true, daysAgo: '2');
        $this->visit('divar', daysAgo: '9');
        Lead::create(['landing_page_id' => $this->page->id, 'name' => 'old', 'phone' => '09121111111', 'status' => 'new'])->forceFill(['created_at' => now()->subDays(5)])->save();

        $preorder = Preorder::first();
        RefundRequest::create(['preorder_id' => $preorder->id, 'payment_id' => $preorder->payments()->first()->id, 'requested_by_type' => 'customer', 'requested_amount_rial' => 1000, 'reason' => 'r', 'status' => 'pending']);
        SmsMessage::create(['mobile' => '09121234567', 'type' => 'template', 'category' => 'transactional', 'provider' => 'smsir', 'status' => 'failed']);

        $this->actingAs($this->user(AdminRole::Finance))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('درخواست بازپرداخت در انتظار بررسی')
            ->assertDontSee('پیامک ناموفق در ۲۴ ساعت اخیر')
            ->assertDontSee('سرنخ جدید پیگیری‌نشده');

        $this->actingAs($this->user(AdminRole::Support))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertDontSee('درخواست بازپرداخت در انتظار بررسی')
            ->assertSee('پیامک ناموفق در ۲۴ ساعت اخیر')
            ->assertSee('سرنخ جدید پیگیری‌نشده بیش از ۲ روز');

        $activity = app(\App\Services\Reports\DashboardService::class)->activity();
        $this->assertSame([1, 2, 1], [$activity['current']['unique_visitors'], $activity['current']['leads'], $activity['current']['paid_count']]);
        $this->assertSame(1, $activity['previous']['unique_visitors']);
    }

    public function test_funnel_and_sources_reports(): void
    {
        $this->visit('Divar', lead: true, paid: true);
        $this->visit('divar', lead: true);
        $this->visit('divar');
        $this->visit('instagram', lead: true);
        $this->visit(null);
        $this->visit('divar', lead: true, daysAgo: '40'); // خارج از ۳۰ روز

        $campaign = Campaign::create(['landing_page_id' => $this->page->id, 'name' => 'd', 'slug' => 'd', 'source' => 'divar', 'status' => 'running']);
        Expense::create(['idea_id' => $this->idea->id, 'campaign_id' => $campaign->id, 'category' => 'ads', 'cost_type' => 'variable', 'amount_rial' => 6_000_000, 'incurred_on' => now()->subDay(), 'description' => 'x']);

        $reports = app(ReportService::class);
        [$from, $to] = $reports->bounds(now()->subDays(29)->toDateString(), now()->toDateString());

        $funnel = $reports->funnel(null, $from, $to);
        $this->assertSame([5, 3, 1, 1], array_map(fn ($key) => $funnel['totals'][$key], ['unique_visitors', 'leads', 'preorders', 'paid_count']));
        $this->assertEquals(60.0, $funnel['steps'][1]['of_visitors']);
        $this->assertSame(['unique_visitors', 'leads', 'preorders', 'paid_count'], array_column($funnel['steps'], 'key'));

        $sources = $reports->sources($from, $to)->keyBy(fn ($row) => $row['source'] ?? 'none');
        $this->assertSame([3, 2, 1, 6_000_000, 3_000_000], [$sources['divar']['unique_visitors'], $sources['divar']['leads'], $sources['divar']['paid_count'], $sources['divar']['spent_rial'], $sources['divar']['cost_per_lead']]);
        $this->assertSame(1, $sources['instagram']['leads']);
        $this->assertSame(1, $sources['none']['unique_visitors']);

        $admin = $this->user(AdminRole::Manager);
        $this->actingAs($admin)->get(route('admin.reports.funnel'))->assertOk()->assertSee('مراحل قیف')->assertSee('60.00% بازدیدکنندگان');
        $this->actingAs($admin)->get(route('admin.reports.sources'))->assertOk()->assertSee('مستقیم / بدون UTM')->assertSee('300,000 تومان');
    }

    public function test_idea_economics_requires_enough_customers_for_cac(): void
    {
        foreach (range(1, 2) as $i) {
            $this->visit('divar', lead: true, paid: true);
        }
        Expense::create(['idea_id' => $this->idea->id, 'category' => 'ads', 'cost_type' => 'variable', 'amount_rial' => 9_000_000, 'incurred_on' => now(), 'description' => 'ads']);
        Expense::create(['idea_id' => $this->idea->id, 'category' => 'development', 'cost_type' => 'fixed', 'amount_rial' => 1_000_000, 'incurred_on' => now(), 'description' => 'dev']);

        $payment = Preorder::first()->payments()->first();
        $request = RefundRequest::create(['preorder_id' => $payment->preorder_id, 'payment_id' => $payment->id, 'requested_by_type' => 'customer', 'requested_amount_rial' => 400_000, 'reason' => 'r', 'status' => 'completed', 'approved_amount_rial' => 400_000]);
        Refund::create(['refund_request_id' => $request->id, 'payment_id' => $payment->id, 'amount_rial' => 400_000, 'method' => 'bank_transfer', 'reference' => 'R1', 'refunded_on' => now()]);

        $reports = app(ReportService::class);
        [$from, $to] = $reports->bounds(now()->subDays(29)->toDateString(), now()->toDateString());

        $row = $reports->ideaEconomics($from, $to)->sole();
        $this->assertSame([10_000_000, 9_000_000, 2_000_000, 400_000, 1_600_000, 1_600_000, 2], [$row['expenses'], $row['ad_spend'], $row['received'], $row['refunded'], $row['net'], $row['recognized'], $row['customers']]);
        $this->assertNull($row['cac']);
        $this->assertTrue($row['cac_insufficient']);
        $this->assertSame(4_500_000, $row['cost_per_lead']);

        $this->visit('divar', lead: true, paid: true);
        $row = $reports->ideaEconomics($from, $to)->sole();
        $this->assertSame(3_000_000, $row['cac']);

        $this->actingAs($this->user(AdminRole::Finance))->get(route('admin.reports.ideas'))->assertOk()->assertSee('300,000 تومان');
    }

    public function test_contacts_and_sms_reports_and_permissions(): void
    {
        $a = Contact::create(['mobile' => '09120000001']);
        $a->forceFill(['first_source' => 'Divar'])->save();
        $a->setMarketingConsent(true, 'test');
        $b = Contact::create(['mobile' => '09120000002']);
        $b->forceFill(['first_source' => 'divar'])->save();
        $b->setMarketingConsent(true, 'test');
        $b->setMarketingConsent(false, 'account');
        Contact::create(['mobile' => '09120000003']);
        $group = ContactGroup::create(['name' => 'vip', 'type' => 'manual']);
        $group->manualMembers()->attach([$a->id, $b->id]);

        foreach ([['sent', 1.5], ['sent', 1.5], ['failed', null]] as [$status, $cost]) {
            SmsMessage::create(['mobile' => '0912', 'template_key' => 'payment_paid', 'type' => 'template', 'category' => 'transactional', 'provider' => 'smsir', 'status' => $status, 'cost' => $cost, 'error' => $status === 'failed' ? 'اعتبار کافی نیست' : null]);
        }
        SmsMessage::create(['mobile' => '0912', 'type' => 'text', 'category' => 'promotional', 'provider' => 'smsir', 'status' => 'sent', 'cost' => 2]);

        $reports = app(ReportService::class);
        [$from, $to] = $reports->bounds(now()->subDays(29)->toDateString(), now()->toDateString());

        $contacts = $reports->contacts($from, $to);
        $this->assertSame(['total' => 3, 'consent' => 1, 'opted_out' => 1], $contacts['all']);
        $divar = $contacts['by_source']->firstWhere('source', 'divar');
        $this->assertSame([2, 1], [$divar['total'], $divar['consent']]);
        $this->assertSame([2, 1], [$contacts['groups'][0]['total'], $contacts['groups'][0]['consent']]);

        $sms = $reports->sms($from, $to);
        $this->assertSame([4, 3, 1, 5.0], [$sms['total'], $sms['sent'], $sms['failed'], $sms['cost']]);
        $this->assertEquals(25.0, $sms['failure_rate']);
        $this->assertSame('اعتبار کافی نیست', $sms['errors'][0]->error);

        $manager = $this->user(AdminRole::Manager);
        $this->actingAs($manager)->get(route('admin.reports.contacts'))->assertOk()->assertSee('vip');
        $this->actingAs($manager)->get(route('admin.reports.sms'))->assertOk()->assertSee('پرداخت موفق پیش‌خرید');

        $support = $this->user(AdminRole::Support);
        foreach (['funnel', 'ideas', 'sources', 'contacts', 'sms'] as $report) {
            $this->actingAs($support)->get(route("admin.reports.{$report}"))->assertForbidden();
        }
    }
}

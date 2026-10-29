<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Contact;
use App\Models\Idea;
use App\Models\LandingPageEvent;
use App\Models\Lead;
use App\Models\Preorder;
use App\Models\RefundRequest;
use App\Models\User;
use App\Models\Visitor;
use App\Services\Reports\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminUxTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    private Preorder $preorder;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');
        config(['app.display_timezone' => 'Asia/Tehran']);
        $this->travelTo(Carbon::parse('2026-10-29 09:00:00', 'UTC'));

        $this->idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'testing']);
        $page = $this->idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't', 'is_active' => true]);
        Contact::create(['mobile' => '09121234567', 'name' => 'علی رضایی']);
        $this->preorder = Preorder::create(['landing_page_id' => $page->id, 'name' => 'سارا محمدی', 'phone' => '09351112233', 'amount_rial' => 1_000_000, 'payment_status' => 'paid', 'public_token' => (string) Str::uuid()]);
        $this->preorder->payments()->create(['amount_rial' => 1_000_000, 'status' => 'paid', 'authority' => 'A77', 'ref_id' => '987654', 'verified_at' => now()]);
    }

    private function user(AdminRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_global_search_respects_permissions_and_normalizes_input(): void
    {
        $admin = $this->user(AdminRole::SuperAdmin);

        // رقم فارسی و نام چندکلمه‌ای
        $this->actingAs($admin)->get(route('admin.search', ['q' => '۰۹۱۲۱۲۳۴۵۶۷']))->assertOk()->assertSee('علی رضایی');
        $this->actingAs($admin)->get(route('admin.search', ['q' => 'علی رضایی']))->assertOk()->assertSee('09121234567');
        $this->actingAs($admin)->get(route('admin.search', ['q' => '#' . $this->preorder->id]))->assertOk()->assertSee('سارا محمدی');
        $this->actingAs($admin)->get(route('admin.search', ['q' => '987654']))->assertOk()->assertSee('کد پیگیری 987654');
        $this->actingAs($admin)->get(route('admin.search', ['q' => 'clubyar']))->assertOk()->assertSee('باشگاه‌یار');
        $this->actingAs($admin)->get(route('admin.search', ['q' => 'x']))->assertOk()->assertSee('دست‌کم دو نویسه');
        $this->actingAs($admin)->get(route('admin.search', ['q' => 'ناموجود']))->assertOk()->assertSee('نتیجه‌ای');

        // پشتیبانی پرداخت‌ها و ایده‌ها را نمی‌بیند
        $support = $this->user(AdminRole::Support);
        $this->actingAs($support)->get(route('admin.search', ['q' => '987654']))->assertOk()->assertDontSee('کد پیگیری 987654');
        $this->actingAs($support)->get(route('admin.search', ['q' => 'clubyar']))->assertOk()->assertDontSee(route('ideas.show', $this->idea));
        $this->actingAs($support)->get(route('admin.search', ['q' => '0935']))->assertOk()->assertSee('سارا محمدی');

        // مالی مخاطبان را نمی‌بیند
        $this->actingAs($this->user(AdminRole::Finance))->get(route('admin.search', ['q' => 'علی']))->assertOk()->assertDontSee('علی رضایی');
    }

    public function test_sensitive_actions_require_confirmation_and_date_fields_have_limits(): void
    {
        $request = RefundRequest::create(['preorder_id' => $this->preorder->id, 'payment_id' => $this->preorder->payments()->first()->id, 'requested_by_type' => 'customer', 'requested_amount_rial' => 500_000, 'reason' => 'r', 'status' => 'approved', 'approved_amount_rial' => 500_000]);

        $this->actingAs($this->user(AdminRole::Finance))->get(route('admin.refunds.show', $request))
            ->assertOk()
            ->assertSee('data-confirm="مبلغ {amount_toman} تومان', false)
            ->assertSee('max="2026-10-29"', false)
            ->assertDontSee('onclick="return confirm', false);

        $manager = $this->user(AdminRole::Manager);
        $this->actingAs($manager)->get(route('ideas.show', $this->idea))
            ->assertSee('data-confirm="وضعیت ایده به «{status}» تغییر کند؟"', false)
            ->assertSee('data-confirm-variant="danger"', false);
        $this->actingAs($manager)->get(route('ideas.refund-policy', $this->idea))->assertSee('data-confirm="نسخه جدید شرایط بازپرداخت', false);

        $layout = $this->actingAs($manager)->get(route('admin.dashboard'))->assertOk();
        $layout->assertSee('js/jalali-datepicker.js', false)->assertSee('js/admin.js', false)->assertSee('role="search"', false);
    }

    public function test_dashboard_daily_trend_uses_tehran_days(): void
    {
        $visitor = Visitor::create(['uuid' => (string) Str::uuid()]);
        $page = $this->preorder->landing_page_id;

        // ۲۲:۰۰ UTC = ۰۱:۳۰ روز بعد تهران؛ دو بازدید یک نفر = یک بازدیدکننده
        foreach (['2026-10-27 22:00:00', '2026-10-27 23:00:00'] as $at) {
            LandingPageEvent::create(['landing_page_id' => $page, 'visitor_id' => $visitor->id, 'event_name' => 'page_view', 'occurred_at' => Carbon::parse($at, 'UTC')]);
        }
        Lead::create(['landing_page_id' => $page, 'name' => 'x', 'phone' => '09120000000'])->forceFill(['created_at' => Carbon::parse('2026-10-27 19:00:00', 'UTC')])->save();

        $daily = collect(app(DashboardService::class)->daily(30))->keyBy('date');

        $this->assertCount(30, $daily);
        $this->assertSame(1, $daily['2026-10-28']['unique_visitors']);
        $this->assertSame(0, $daily['2026-10-27']['unique_visitors']);
        $this->assertSame(1, $daily['2026-10-27']['leads']);
        $this->assertSame(1, $daily['2026-10-29']['paid_count']);

        $this->actingAs($this->user(AdminRole::Manager))->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('روند روزانه ۳۰ روز اخیر')
            ->assertSee('1405/08/06: 1');
    }
}

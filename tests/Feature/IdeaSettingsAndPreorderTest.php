<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\RefundPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use LogicException;
use Tests\TestCase;

class IdeaSettingsAndPreorderTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    private LandingPage $page;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idea = Idea::create([
            'name' => 'باشگاه‌یار',
            'slug' => 'clubyar',
            'status' => 'testing',
        ]);

        $this->page = $this->idea->landingPages()->create([
            'name' => 'صفحه',
            'slug' => 'clubyar',
            'title' => 'عنوان',
            'is_active' => true,
        ]);
    }

    private function actingAsManager(): self
    {
        return $this->actingAs(User::factory()->create([
            'role' => AdminRole::Manager,
        ]));
    }

    private function openPreorders(int $amountRial = 1_200_000): RefundPolicy
    {
        $this->idea->update(['preorder_amount_rial' => $amountRial]);

        return $this->idea->refundPolicies()->create([
            'version' => 1,
            'is_refundable' => true,
            'refund_window_days' => 14,
            'terms' => 'تا ۱۴ روز قابل بازپرداخت است.',
        ]);
    }

    private function submitPreorder(array $overrides = [])
    {
        return $this->post(route('preorders.store', $this->page->slug), $overrides + [
            'name' => 'علی',
            'phone' => '09123456789',
            'consent' => '1',
            'accept_terms' => '1',
            'refund_policy_version' => 1,
        ]);
    }

    public function test_settings_accept_persian_digits_and_store_rial(): void
    {
        $this->actingAsManager()
            ->put(route('ideas.settings.update', $this->idea), [
                'budget_toman' => '۵,۰۰۰,۰۰۰',
                'preorder_amount_toman' => '120٬000',
                'test_starts_on' => '2026-11-01',
                'test_ends_on' => '2026-11-30',
                'lead_goal' => '۲۰۰',
                'paid_preorder_goal' => '20',
            ])->assertRedirect(route('ideas.show', $this->idea));

        $idea = $this->idea->fresh();
        $this->assertSame(50_000_000, $idea->budget_rial);
        $this->assertSame(1_200_000, $idea->preorder_amount_rial);
        $this->assertSame(200, $idea->lead_goal);
        $this->assertSame('2026-11-30', $idea->test_ends_on->format('Y-m-d'));

        $this->get(route('ideas.settings', $this->idea))
            ->assertSee('value="120,000"', false);
    }

    public function test_invalid_settings_are_rejected(): void
    {
        $this->actingAsManager()
            ->put(route('ideas.settings.update', $this->idea), [
                'preorder_amount_toman' => '500',
                'test_starts_on' => '2026-11-10',
                'test_ends_on' => '2026-11-01',
                'lead_goal' => 'abc',
            ])->assertSessionHasErrors(['preorder_amount_toman', 'test_ends_on', 'lead_goal']);

        $this->assertNull($this->idea->fresh()->preorder_amount_rial);
    }

    public function test_refund_policy_edits_create_new_versions(): void
    {
        $this->actingAsManager();

        $payload = [
            'is_refundable' => '1',
            'refund_window_days' => '7',
            'terms' => 'نسخه اول',
        ];

        $this->post(route('ideas.refund-policy.store', $this->idea), $payload)->assertRedirect();
        $this->post(route('ideas.refund-policy.store', $this->idea), $payload)->assertRedirect();
        $this->assertSame(1, $this->idea->refundPolicies()->count());

        $this->post(route('ideas.refund-policy.store', $this->idea), [
            'is_refundable' => '0',
            'refund_window_days' => '7',
            'terms' => 'نسخه دوم',
        ])->assertRedirect();

        $current = $this->idea->fresh()->currentRefundPolicy;
        $this->assertSame(2, $current->version);
        $this->assertFalse($current->is_refundable);
        $this->assertNull($current->refund_window_days);
        $this->assertSame('نسخه اول', $this->idea->refundPolicies()->where('version', 1)->value('terms'));
    }

    public function test_refundable_policy_requires_window(): void
    {
        $this->actingAsManager()
            ->post(route('ideas.refund-policy.store', $this->idea), [
                'is_refundable' => '1',
                'terms' => 'x',
            ])->assertSessionHasErrors('refund_window_days');
    }

    public function test_refund_policy_rows_are_immutable(): void
    {
        $policy = $this->openPreorders();

        $this->expectException(LogicException::class);
        $policy->update(['terms' => 'تغییر']);
    }

    public function test_preorders_are_closed_without_amount_or_policy(): void
    {
        $this->get('/l/clubyar')->assertOk()->assertDontSee('id="preorder-form"', false);
        $this->submitPreorder()->assertNotFound();

        $this->idea->update(['preorder_amount_rial' => 100000]);
        $this->get('/l/clubyar')->assertDontSee('id="preorder-form"', false);

        $this->actingAsManager()
            ->get(route('ideas.show', $this->idea))
            ->assertSee('شرایط بازپرداخت تعیین نشده است.');
    }

    public function test_public_page_shows_amount_and_terms(): void
    {
        $this->openPreorders();

        $this->get('/l/clubyar')
            ->assertSee('id="preorder-form"', false)
            ->assertSee('120,000 تومان')
            ->assertSee('تا ۱۴ روز قابل بازپرداخت است.')
            ->assertSee('name="refund_policy_version" value="1"', false);
    }

    public function test_preorder_snapshots_amount_and_policy(): void
    {
        $policy = $this->openPreorders(1_200_000);

        $this->submitPreorder()->assertRedirect();

        $preorder = Preorder::sole();
        $this->assertSame(1_200_000, $preorder->amount_rial);
        $this->assertSame($policy->id, $preorder->refund_policy_id);
        $this->assertNotNull($preorder->terms_accepted_at);

        // تغییر بعدی مبلغ و شرایط روی سفارش ثبت‌شده اثری ندارد
        $this->idea->update(['preorder_amount_rial' => 9_990_000]);
        $this->idea->refundPolicies()->create([
            'version' => 2,
            'is_refundable' => false,
            'terms' => 'جدید',
        ]);

        $preorder->refresh();
        $this->assertSame(1_200_000, $preorder->amount_rial);
        $this->assertSame($policy->id, $preorder->refund_policy_id);
    }

    public function test_preorder_requires_terms_acceptance(): void
    {
        $this->openPreorders();

        $this->submitPreorder(['accept_terms' => null])
            ->assertSessionHasErrorsIn('preorder', 'accept_terms');

        $this->assertSame(0, Preorder::count());
    }

    public function test_preorder_with_stale_policy_version_is_rejected(): void
    {
        $this->openPreorders();
        $this->idea->refundPolicies()->create([
            'version' => 2,
            'is_refundable' => false,
            'terms' => 'جدید',
        ]);

        $this->submitPreorder(['refund_policy_version' => 1])
            ->assertSessionHasErrorsIn('preorder', 'accept_terms');

        $this->assertSame(0, Preorder::count());
    }

    public function test_payment_request_uses_snapshot_rial_without_double_conversion(): void
    {
        config([
            'services.zarinpal.merchant_id' => 'test-merchant',
            'services.zarinpal.sandbox' => true,
        ]);

        Http::fake([
            '*/pg/v4/payment/request.json' => Http::response([
                'data' => ['code' => 100, 'authority' => 'A0000TEST'],
            ]),
        ]);

        $this->openPreorders(1_200_000);
        $this->submitPreorder();
        $preorder = Preorder::sole();

        // تغییر مبلغ ایده پس از ثبت سفارش نباید روی پرداخت اثر بگذارد
        $this->idea->update(['preorder_amount_rial' => 5_000_000]);

        $this->get(route('preorders.checkout', $preorder->public_token))
            ->assertOk()
            ->assertSee('120,000 تومان')
            ->assertSee('قابل بازپرداخت تا 14 روز');

        $this->post(route('preorders.pay', $preorder->public_token))
            ->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/A0000TEST');

        Http::assertSent(fn ($request) => $request['amount'] === 1_200_000);
        $this->assertSame(1_200_000, Payment::sole()->amount_rial);
    }
}

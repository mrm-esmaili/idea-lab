<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Expense;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Models\User;
use App\Services\CampaignStatsService;
use App\Services\FinanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class RefundAndFinanceTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    private LandingPage $page;

    private Contact $contact;

    private Preorder $preorder;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');

        $this->idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'testing', 'preorder_amount_rial' => 1_200_000]);
        $this->page = $this->idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't', 'is_active' => true]);
        $policy = $this->idea->refundPolicies()->create(['version' => 1, 'is_refundable' => true, 'refund_window_days' => 14, 'terms' => 'تا ۱۴ روز']);

        $this->contact = Contact::create(['mobile' => '09121234567', 'name' => 'علی']);

        $this->preorder = Preorder::create([
            'landing_page_id' => $this->page->id,
            'contact_id' => $this->contact->id,
            'refund_policy_id' => $policy->id,
            'name' => 'علی',
            'phone' => '09121234567',
            'amount_rial' => 1_200_000,
            'payment_status' => 'paid',
            'public_token' => (string) Str::uuid(),
        ]);

        $this->payment = $this->preorder->payments()->create([
            'amount_rial' => 1_200_000,
            'status' => 'paid',
            'authority' => 'A1',
            'ref_id' => '555',
            'verified_at' => now(),
        ]);
    }

    private function asContact(?Contact $contact = null): self
    {
        return $this->actingAs($contact ?? $this->contact, 'contact')
            ->withSession(['contact_login_at' => now()->getTimestamp()]);
    }

    private function finance(): User
    {
        return User::factory()->create(['role' => AdminRole::Finance]);
    }

    private function customerRequest(): RefundRequest
    {
        $this->asContact()
            ->post(route('account.refund', $this->preorder->id), ['reason' => 'محصول را لازم ندارم'])
            ->assertRedirect(route('account.dashboard'));

        return RefundRequest::sole();
    }

    public function test_eligible_customer_can_request_full_remaining_amount(): void
    {
        $this->asContact()->get(route('account.dashboard'))->assertSee('درخواست بازپرداخت')->assertSee('۱۲۰٬۰۰۰ تومان');

        $request = $this->customerRequest();

        $this->assertSame('pending', $request->status);
        $this->assertSame(1_200_000, $request->requested_amount_rial);
        $this->assertSame('customer', $request->requested_by_type);
        $this->assertSame($this->preorder->refund_policy_id, $request->refund_policy_id);

        $log = AuditLog::sole();
        $this->assertSame('refund.requested', $log->action);
        $this->assertSame($this->contact->id, $log->contact_id);

        // درخواست دوم تا بسته‌شدن درخواست اول ممکن نیست
        $this->asContact()->post(route('account.refund', $this->preorder->id), ['reason' => 'دوباره درخواست'])
            ->assertSessionHasErrors('refund_' . $this->preorder->id);
        $this->assertSame(1, RefundRequest::count());
    }

    public function test_non_refundable_or_expired_orders_cannot_be_requested_online(): void
    {
        $this->travel(15)->days();

        $this->asContact()->get(route('account.dashboard'))->assertSee('مهلت درخواست بازپرداخت');
        $this->asContact()->post(route('account.refund', $this->preorder->id), ['reason' => 'دیر شده است'])
            ->assertSessionHasErrors('refund_' . $this->preorder->id);

        $this->travelBack();

        $nonRefundable = $this->idea->refundPolicies()->create(['version' => 2, 'is_refundable' => false, 'terms' => 'غیرقابل بازپرداخت']);
        $this->preorder->update(['refund_policy_id' => $nonRefundable->id]);

        $this->asContact()->post(route('account.refund', $this->preorder->id), ['reason' => 'لطفاً برگردانید'])
            ->assertSessionHasErrors('refund_' . $this->preorder->id);

        $this->assertSame(0, RefundRequest::count());
    }

    public function test_customer_cannot_request_refund_for_someone_elses_order(): void
    {
        $other = Contact::create(['mobile' => '09129999999']);

        $this->asContact($other)
            ->post(route('account.refund', $this->preorder->id), ['reason' => 'سفارش من نیست'])
            ->assertNotFound();
    }

    public function test_full_lifecycle_with_partial_refunds_and_over_refund_protection(): void
    {
        $request = $this->customerRequest();
        $finance = $this->finance();
        $this->actingAs($finance, 'web');

        // مبلغ تأیید نمی‌تواند از مبلغ درخواست بیشتر باشد
        $this->post(route('admin.refunds.approve', $request), ['amount_toman' => '130,000'])->assertSessionHasErrors('refund');

        $this->post(route('admin.refunds.approve', $request), ['amount_toman' => '۶۰,۰۰۰'])->assertSessionHasNoErrors();
        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame(600_000, $request->approved_amount_rial);

        // تأیید به‌تنهایی وضعیت «بازپرداخت‌شده» نمی‌دهد
        $this->assertSame('paid', $this->preorder->fresh()->payment_status);

        // مرجع الزامی است و اجرا نباید از مبلغ تأییدشده بیشتر باشد
        $execute = fn (array $data) => $this->post(route('admin.refunds.execute', $request), $data + [
            'amount_toman' => '60,000', 'method' => 'bank_transfer', 'reference' => 'BANK-1', 'refunded_on' => now()->toDateString(),
        ]);

        $execute(['reference' => ''])->assertSessionHasErrors('reference');
        $execute(['amount_toman' => '70,000'])->assertSessionHasErrors('refund');
        $execute(['refunded_on' => now()->addDay()->toDateString()])->assertSessionHasErrors('refunded_on');
        $execute([])->assertSessionHasNoErrors();

        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame('partially_refunded', $this->preorder->fresh()->payment_status);
        $this->assertSame($finance->id, Refund::sole()->executed_by);

        // باقی‌مانده با درخواست مدیر
        $this->post(route('admin.refunds.store', $this->preorder), [
            'payment_id' => $this->payment->id, 'amount_toman' => '60,001', 'reason' => 'بیش از باقی‌مانده',
        ])->assertSessionHasErrors('refund');

        $this->post(route('admin.refunds.store', $this->preorder), [
            'payment_id' => $this->payment->id, 'amount_toman' => '60,000', 'reason' => 'بازپرداخت باقی‌مانده',
        ])->assertRedirect();

        $second = RefundRequest::latest('id')->first();
        $this->assertSame('admin', $second->requested_by_type);

        $this->post(route('admin.refunds.approve', $second), ['amount_toman' => '60,000']);

        // همان مرجع برای همان پرداخت دوباره پذیرفته نمی‌شود
        $this->post(route('admin.refunds.execute', $second), [
            'amount_toman' => '60,000', 'method' => 'card_to_card', 'reference' => 'BANK-1', 'refunded_on' => now()->toDateString(),
        ])->assertSessionHasErrors('refund');

        $this->post(route('admin.refunds.execute', $second), [
            'amount_toman' => '60,000', 'method' => 'card_to_card', 'reference' => 'BANK-2', 'refunded_on' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame('refunded', $this->preorder->fresh()->payment_status);
        $this->assertSame(1_200_000, (int) Refund::sum('amount_rial'));

        // دیگر چیزی برای بازپرداخت نمانده است
        $this->post(route('admin.refunds.store', $this->preorder), [
            'payment_id' => $this->payment->id, 'amount_toman' => '1', 'reason' => 'اضافه',
        ])->assertSessionHasErrors();

        $this->assertSame(
            ['refund.requested', 'refund.approved', 'refund.executed', 'refund.requested', 'refund.approved', 'refund.executed'],
            AuditLog::orderBy('id')->pluck('action')->all()
        );

        // رسید و حساب مشتری وضعیت را نشان می‌دهند
        $this->get(route('preorders.receipt', $this->preorder->public_token))->assertSee('بازپرداخت شده است');
        $this->asContact()->get(route('account.dashboard'))->assertSee('بازپرداخت‌شده');
    }

    public function test_rejection_requires_reason_and_is_shown_to_customer(): void
    {
        $request = $this->customerRequest();

        $this->actingAs($this->finance(), 'web');
        $this->post(route('admin.refunds.reject', $request), ['note' => ''])->assertSessionHasErrors('note');
        $this->post(route('admin.refunds.reject', $request), ['note' => 'خارج از شرایط'])->assertSessionHasNoErrors();

        $this->assertSame('rejected', $request->fresh()->status);
        $this->post(route('admin.refunds.execute', $request), [
            'amount_toman' => '1', 'method' => 'cash', 'reference' => 'X', 'refunded_on' => now()->toDateString(),
        ])->assertSessionHasErrors('refund');

        $this->asContact()->get(route('account.dashboard'))->assertSee('ردشده')->assertSee('خارج از شرایط');
    }

    public function test_finance_pages_require_finance_permission(): void
    {
        $request = $this->customerRequest();
        $support = User::factory()->create(['role' => AdminRole::Support]);

        foreach ([route('admin.refunds.index'), route('admin.refunds.show', $request), route('admin.expenses.index'), route('admin.finance.index')] as $url) {
            $this->actingAs($support, 'web')->get($url)->assertForbidden();
        }

        $finance = $this->finance();
        $this->actingAs($finance, 'web')->get(route('admin.refunds.index'))->assertOk()->assertSee('علی');
        $this->actingAs($finance, 'web')->get(route('admin.refunds.show', $request))->assertOk()->assertSee('تا ۱۴ روز')->assertSee('ثبت درخواست بازپرداخت');
        $this->actingAs($finance, 'web')->get(route('admin.preorders.show', $this->preorder))->assertOk()->assertSee('درخواست مشتری');
    }

    public function test_expenses_crud_audit_and_campaign_actual_cost(): void
    {
        $campaign = Campaign::create(['landing_page_id' => $this->page->id, 'name' => 'دیوار', 'slug' => 'divar', 'status' => 'running', 'budget_rial' => 50_000_000]);
        $this->page->leads()->create(['name' => 'l', 'phone' => '09120000001', 'campaign' => 'divar']);
        $this->page->leads()->create(['name' => 'l', 'phone' => '09120000002', 'campaign' => 'divar']);

        $this->actingAs($this->finance(), 'web');

        $this->post(route('admin.expenses.store'), [
            'campaign_id' => $campaign->id, 'category' => 'ads', 'cost_type' => 'variable',
            'amount_toman' => '۲۰۰,۰۰۰', 'incurred_on' => now()->toDateString(), 'description' => 'آگهی دیوار',
        ])->assertRedirect(route('admin.expenses.index'));

        $expense = Expense::sole();
        $this->assertSame($this->idea->id, $expense->idea_id, 'هزینه کمپین به ایده همان کمپین تعلق دارد');
        $this->assertSame(2_000_000, $expense->amount_rial);

        $other = Idea::create(['name' => 'دیگر', 'slug' => 'other', 'status' => 'draft']);
        $this->post(route('admin.expenses.store'), [
            'idea_id' => $other->id, 'campaign_id' => $campaign->id, 'category' => 'ads', 'cost_type' => 'fixed',
            'amount_toman' => '1000', 'incurred_on' => now()->toDateString(), 'description' => 'x',
        ])->assertSessionHasErrors('campaign_id');

        $stats = app(CampaignStatsService::class)->report(collect([$this->page->id]), collect([$campaign]))['campaigns'][$campaign->id];
        $this->assertSame('actual', $stats['cost_basis']);
        $this->assertSame(1_000_000, $stats['cost_per_lead_rial']);

        $this->put(route('admin.expenses.update', $expense), [
            'idea_id' => $this->idea->id, 'category' => 'ads', 'cost_type' => 'fixed',
            'amount_toman' => '300000', 'incurred_on' => now()->toDateString(), 'description' => 'اصلاح',
        ])->assertRedirect();
        $this->delete(route('admin.expenses.destroy', $expense))->assertRedirect();

        $this->assertSame(['expense.created', 'expense.updated', 'expense.deleted'], AuditLog::orderBy('id')->pluck('action')->all());
        $this->assertSame(2_000_000, AuditLog::where('action', 'expense.updated')->sole()->data['before']['amount_rial']);
    }

    public function test_finance_report_definitions(): void
    {
        $request = $this->customerRequest();
        $finance = $this->finance();
        $this->actingAs($finance, 'web');

        $this->post(route('admin.refunds.approve', $request), ['amount_toman' => '20,000']);
        $this->post(route('admin.refunds.execute', $request), [
            'amount_toman' => '20,000', 'method' => 'bank_transfer', 'reference' => 'R1', 'refunded_on' => now()->toDateString(),
        ]);

        Expense::create(['idea_id' => $this->idea->id, 'category' => 'ads', 'cost_type' => 'variable', 'amount_rial' => 300_000, 'incurred_on' => now(), 'description' => 'a']);
        Expense::create(['category' => 'operations', 'cost_type' => 'fixed', 'amount_rial' => 100_000, 'incurred_on' => now(), 'description' => 'هاست']);

        // تراکنش در انتظار و درخواست باز دیگر
        $this->preorder->payments()->create(['amount_rial' => 1_200_000, 'status' => 'pending', 'authority' => 'A2']);

        $report = app(FinanceReportService::class)->build(null, null, null);
        $totals = $report['totals'];

        $this->assertSame(1_200_000, $totals['received']);
        $this->assertSame(200_000, $totals['refunded']);
        $this->assertSame(1_000_000, $totals['net']);
        $this->assertSame(0, $totals['recognized']);
        $this->assertSame(1_000_000, $totals['deferred']);
        $this->assertSame(400_000, $totals['expenses']);
        $this->assertSame(600_000, $totals['cash']);
        $this->assertSame(1_200_000, $report['pending']['payments']);

        $this->idea->update(['status' => 'launched']);
        $this->assertSame(1_000_000, app(FinanceReportService::class)->build(null, null, null)['totals']['recognized']);

        $future = app(FinanceReportService::class)->build(now()->addDays(5), now()->addDays(6), null);
        $this->assertSame(0, $future['totals']['received']);

        $this->get(route('admin.finance.index'))->assertOk()->assertSee('100,000 تومان')->assertSee('عمومی');
    }

    public function test_audit_logs_are_append_only(): void
    {
        $this->customerRequest();

        $this->expectException(LogicException::class);
        AuditLog::sole()->update(['action' => 'tampered']);
    }

    public function test_refunded_segment_filter(): void
    {
        $this->preorder->update(['payment_status' => 'refunded']);

        $manager = User::factory()->create(['role' => AdminRole::Manager]);

        $this->assertSame([$this->contact->mobile], $this->actingAs($manager)->get('/admin/contacts?payment=refunded')->viewData('contacts')->pluck('mobile')->all());
        $this->assertSame([], $this->actingAs($manager)->get('/admin/contacts?payment=paid')->viewData('contacts')->pluck('mobile')->all());
    }
}

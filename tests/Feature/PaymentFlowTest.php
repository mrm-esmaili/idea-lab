<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\LandingPageEvent;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\User;
use App\Services\IdeaStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    private Preorder $preorder;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.zarinpal.merchant_id' => 'test-merchant',
            'services.zarinpal.sandbox' => true,
        ]);

        $this->idea = Idea::create([
            'name' => 'باشگاه‌یار',
            'slug' => 'clubyar',
            'status' => 'testing',
            'preorder_amount_rial' => 1_200_000,
        ]);

        $page = $this->idea->landingPages()->create([
            'name' => 'صفحه',
            'slug' => 'clubyar',
            'title' => 'عنوان',
        ]);

        $this->preorder = Preorder::create([
            'landing_page_id' => $page->id,
            'name' => 'علی',
            'phone' => '09123456789',
            'status' => 'requested',
            'amount_rial' => 1_200_000,
            'public_token' => (string) Str::uuid(),
        ]);
    }

    private int $authorityCounter = 0;

    private array $requestResponse = [];

    /** پاسخ verify یا 'unreachable' */
    private array|string $verifyResponse = [];

    /**
     * یک fake قابل‌تنظیم؛ مانند درگاه واقعی هر request یک authority یکتا می‌گیرد.
     */
    private function fakeGateway(array $request = null, array|string $verify = null): void
    {
        $this->requestResponse = $request ?? [];
        $this->verifyResponse = $verify ?? [
            'data' => ['code' => 100, 'message' => 'Verified', 'card_hash' => 'SECRET-HASH', 'card_pan' => '502229******5995', 'ref_id' => 201, 'fee' => 0],
            'errors' => [],
        ];

        Http::fake(function ($request) {
            if (str_ends_with($request->url(), 'request.json')) {
                if ($this->requestResponse !== []) {
                    return Http::response($this->requestResponse);
                }

                $this->authorityCounter++;

                return Http::response([
                    'data' => ['code' => 100, 'message' => 'Success', 'authority' => $this->authority($this->authorityCounter), 'fee' => 0],
                    'errors' => [],
                ]);
            }

            if ($this->verifyResponse === 'unreachable') {
                return Http::failedConnection();
            }

            return Http::response($this->verifyResponse);
        });
    }

    private function authority(int $n): string
    {
        return 'A' . str_pad((string) $n, 35, '0', STR_PAD_LEFT);
    }

    private function pay()
    {
        return $this->post(route('preorders.pay', $this->preorder->public_token));
    }

    private function gatewayReturn(string $status = 'OK', ?string $authority = null)
    {
        $authority ??= $this->authority(1);

        return $this->get(route('payments.zarinpal.callback', ['Authority' => $authority, 'Status' => $status]));
    }

    private function successEvents(): int
    {
        return LandingPageEvent::where('event_name', 'payment_success')->count();
    }

    public function test_successful_payment_flow(): void
    {
        $this->fakeGateway();

        $this->pay()->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/' . $this->authority(1));

        $payment = Payment::sole();
        $this->assertSame('pending', $payment->status);
        $this->assertSame(1_200_000, $payment->amount_rial);

        $this->gatewayReturn()->assertRedirect(route('preorders.receipt', $this->preorder->public_token));

        $payment->refresh();
        $this->assertSame('paid', $payment->status);
        $this->assertSame('201', $payment->ref_id);
        $this->assertSame('502229******5995', $payment->card_pan);
        $this->assertNotNull($payment->verified_at);
        $this->assertStringNotContainsString('SECRET-HASH', json_encode($payment->gateway_response));
        $this->assertTrue($this->preorder->fresh()->isPaid());
        $this->assertSame('requested', $this->preorder->fresh()->status);
        $this->assertSame(1, $this->successEvents());

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'verify.json') && $r['amount'] === 1_200_000);

        $this->get(route('preorders.receipt', $this->preorder->public_token))
            ->assertOk()
            ->assertSee('رسید پرداخت')
            ->assertSee('201')
            ->assertSee('۱۲۰٬۰۰۰ تومان');
    }

    public function test_repeated_callback_is_idempotent(): void
    {
        $this->fakeGateway();
        $this->pay();

        $this->gatewayReturn();
        $this->gatewayReturn();
        $this->gatewayReturn();

        $this->assertSame(1, $this->successEvents());
        Http::assertSentCount(2); // یک request و یک verify
    }

    public function test_second_pay_click_reuses_pending_authority(): void
    {
        $this->fakeGateway();

        $this->pay()->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/' . $this->authority(1));
        $this->pay()->assertRedirect('https://sandbox.zarinpal.com/pg/StartPay/' . $this->authority(1));

        $this->assertSame(1, Payment::count());
        Http::assertSentCount(1);
    }

    public function test_pending_older_than_reuse_window_gets_new_payment(): void
    {
        $this->fakeGateway();
        $this->pay();

        $this->travel(11)->minutes();
        $this->pay();

        $this->assertSame(2, Payment::count());
    }

    public function test_paid_preorder_cannot_be_paid_again(): void
    {
        $this->fakeGateway();
        $this->pay();
        $this->gatewayReturn();

        $this->pay()->assertRedirect(route('preorders.receipt', $this->preorder->public_token));
        $this->get(route('preorders.checkout', $this->preorder->public_token))
            ->assertRedirect(route('preorders.receipt', $this->preorder->public_token));

        $this->assertSame(1, Payment::count());
    }

    public function test_gateway_request_error_is_recorded(): void
    {
        $this->fakeGateway(request: [
            'data' => [],
            'errors' => ['code' => -9, 'message' => 'The input params invalid, validation error.'],
        ]);

        $this->pay()->assertRedirect(route('preorders.checkout', $this->preorder->public_token));

        $payment = Payment::sole();
        $this->assertSame('failed', $payment->status);
        $this->assertStringContainsString('-9', $payment->failure_reason);

        $this->get(route('preorders.checkout', $this->preorder->public_token))
            ->assertSee('اتصال به درگاه پرداخت ممکن نشد');
    }

    public function test_duplicate_authority_from_gateway_fails_gracefully(): void
    {
        $this->fakeGateway(request: ['data' => ['code' => 100, 'authority' => 'ASAME']]);
        $this->pay();

        $this->travel(11)->minutes();
        $this->pay()->assertRedirect(route('preorders.checkout', $this->preorder->public_token));

        $this->assertSame(['pending', 'failed'], Payment::orderBy('id')->pluck('status')->all());
        $this->assertSame('ASAME', Payment::orderBy('id')->first()->authority);
    }

    public function test_cancelled_at_gateway(): void
    {
        $this->fakeGateway();
        $this->pay();

        $this->gatewayReturn('NOK')->assertRedirect(route('preorders.receipt', $this->preorder->public_token));

        $this->assertSame('cancelled', Payment::sole()->status);
        $this->assertFalse($this->preorder->fresh()->isPaid());
        Http::assertSentCount(1); // verify فراخوانی نشد

        $this->get(route('preorders.receipt', $this->preorder->public_token))
            ->assertSee('پرداخت لغو شد');

        // پس از لغو، تلاش دوباره تراکنش جدید می‌سازد
        $this->pay();
        $this->assertSame(2, Payment::count());
    }

    public function test_definitive_verify_failure_marks_failed(): void
    {
        $this->fakeGateway(verify: [
            'data' => [],
            'errors' => ['code' => -51, 'message' => 'Session is not valid, session is not active paid try.'],
        ]);

        $this->pay();
        $this->gatewayReturn();

        $payment = Payment::sole();
        $this->assertSame('failed', $payment->status);
        $this->assertStringContainsString('-51', $payment->failure_reason);
        $this->assertFalse($this->preorder->fresh()->isPaid());
        $this->assertSame(0, $this->successEvents());
    }

    public function test_unreachable_verify_stays_pending_and_admin_can_reverify(): void
    {
        $this->fakeGateway(verify: 'unreachable');
        $this->pay();
        $this->gatewayReturn();

        $payment = Payment::sole();
        $this->assertSame('pending', $payment->status);
        $this->assertNotNull($payment->failure_reason);

        $this->get(route('preorders.receipt', $this->preorder->public_token))
            ->assertSee('در حال بررسی پرداخت')
            ->assertSee('لطفاً دوباره پرداخت نکنید');

        // درگاه دوباره در دسترس است
        $this->verifyResponse = ['data' => ['code' => 100, 'ref_id' => 301]];

        $this->actingAs(User::factory()->create(['role' => AdminRole::Support]))
            ->post(route('admin.payments.verify', $payment))
            ->assertRedirect(route('admin.preorders.show', $this->preorder));

        $this->assertSame('paid', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->failure_reason);
        $this->assertTrue($this->preorder->fresh()->isPaid());
    }

    public function test_unknown_or_malformed_authority(): void
    {
        $this->fakeGateway();

        $this->gatewayReturn('OK', 'UNKNOWN123')->assertRedirect(route('payments.result', ['status' => 'failed']));
        $this->gatewayReturn('OK', "A1' OR 1=1")->assertRedirect(route('payments.result', ['status' => 'failed']));

        Http::assertNothingSent();
    }

    public function test_duplicate_successful_payments_are_recorded_and_flagged(): void
    {
        $this->fakeGateway();
        $this->pay();

        // تراکنش دوم پس از پنجره استفاده مجدد، با authority دیگر
        $this->travel(11)->minutes();
        $this->pay();

        $this->gatewayReturn('OK', $this->authority(1));
        $this->gatewayReturn('OK', $this->authority(2));

        $this->assertSame(2, Payment::where('status', 'paid')->count());
        $this->assertSame(1, $this->successEvents());

        $stats = app(IdeaStatsService::class)->forLandingPages($this->idea);
        $page = $this->preorder->landing_page_id;
        $this->assertSame(1, $stats[$page]['paid_count']);
        $this->assertSame(2_400_000, $stats[$page]['paid_rial']);

        $this->actingAs(User::factory()->create(['role' => AdminRole::Finance]))
            ->get('/admin/payments')
            ->assertOk()
            ->assertSee('1 سفارش بیش از یک پرداخت موفق دارد');
    }

    public function test_admin_cannot_set_payment_status_or_legacy_converted(): void
    {
        $this->actingAs(User::factory()->create(['role' => AdminRole::Support]));

        $this->patch(route('admin.preorders.update', $this->preorder), [
            'status' => 'contacted',
            'payment_status' => 'paid',
        ])->assertRedirect();

        $this->assertSame('contacted', $this->preorder->fresh()->status);
        $this->assertFalse($this->preorder->fresh()->isPaid());

        $this->patch(route('admin.preorders.update', $this->preorder), [
            'status' => 'converted',
        ])->assertSessionHasErrors('status');
    }

    public function test_payment_report_summary_filters_and_permissions(): void
    {
        $this->preorder->payments()->createMany([
            ['amount_rial' => 1_200_000, 'status' => 'paid', 'authority' => 'P1', 'ref_id' => '1'],
            ['amount_rial' => 1_200_000, 'status' => 'failed', 'authority' => 'P2'],
            ['amount_rial' => 1_200_000, 'status' => 'pending', 'authority' => 'P3'],
        ]);

        Payment::where('authority', 'P2')->update(['created_at' => now()->subDays(10)]);

        $this->actingAs(User::factory()->create(['role' => AdminRole::Support]))
            ->get('/admin/payments')
            ->assertForbidden();

        $finance = User::factory()->create(['role' => AdminRole::Finance]);

        $response = $this->actingAs($finance)->get('/admin/payments');
        $summary = $response->viewData('summary');
        $this->assertSame(1, (int) $summary['paid']->total);
        $this->assertSame(1_200_000, (int) $summary['paid']->amount_rial);
        $this->assertSame(1, (int) $summary['failed']->total);

        $recent = $this->actingAs($finance)
            ->get('/admin/payments?from=' . now()->subDays(2)->format('Y-m-d'))
            ->viewData('summary');
        $this->assertFalse($recent->has('failed'));

        $listed = $this->actingAs($finance)
            ->get('/admin/payments?status=pending')
            ->viewData('payments');
        $this->assertSame(['P3'], $listed->pluck('authority')->all());

        // خلاصه با فیلتر وضعیت تغییر نمی‌کند؛ فقط فهرست فیلتر می‌شود
        $this->assertSame(1, (int) $this->actingAs($finance)
            ->get('/admin/payments?status=pending')
            ->viewData('summary')['paid']->total);
    }
}

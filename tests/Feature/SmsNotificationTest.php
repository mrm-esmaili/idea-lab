<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\SmsBroadcast;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Payments\PreorderPaymentService;
use App\Services\Refunds\RefundService;
use App\Services\Sms\BroadcastService;
use App\Services\Sms\SmsDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class SmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    private LandingPage $page;

    private Contact $contact;

    /** پاسخ شبیه‌سازی‌شده SMS.ir: 'ok' | 'down' | 'reject' */
    private string $smsMode = 'ok';

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');

        // ساعت ۱۱:۳۰ تهران؛ داخل ساعات مجاز پیامک تبلیغاتی
        $this->travelTo(Carbon::parse('2026-10-29 08:00:00', 'UTC'));

        config([
            'app.display_timezone' => 'Asia/Tehran',
            'services.sms.driver' => 'smsir',
            'services.sms.smsir.api_key' => 'test-key',
            'services.sms.smsir.line_number' => '300012345',
            'services.zarinpal.merchant_id' => 'test-merchant',
            'services.zarinpal.sandbox' => true,
        ]);

        Http::fake(function (HttpRequest $request) {
            if (str_contains($request->url(), 'zarinpal')) {
                return Http::response(['data' => ['code' => 100, 'ref_id' => 777, 'card_pan' => '6037******1111'], 'errors' => []]);
            }

            return match ($this->smsMode) {
                'down' => Http::response('', 503),
                'reject' => Http::response(['status' => 0, 'message' => 'اعتبار کافی نیست'], 400),
                default => str_ends_with($request->url(), 'send/bulk')
                    ? Http::response(['status' => 1, 'data' => ['packId' => 'p1', 'messageIds' => range(1, count($request['mobiles'])), 'cost' => 2.0 * count($request['mobiles'])]])
                    : Http::response(['status' => 1, 'data' => ['messageId' => 9001, 'cost' => 1.5]]),
            };
        });

        $this->idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'building', 'preorder_amount_rial' => 1_200_000]);
        $this->page = $this->idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't', 'is_active' => true]);
        $this->contact = Contact::create(['mobile' => '09121234567', 'name' => 'علی']);
    }

    private function enable(string ...$keys): void
    {
        foreach ($keys as $index => $key) {
            SmsTemplate::where('key', $key)->update(['enabled' => true, 'provider_template_id' => 100 + $index]);
        }
    }

    private function paidPreorder(?Contact $contact = null, string $status = 'paid'): Preorder
    {
        $contact ??= $this->contact;
        $policy = $this->idea->refundPolicies()->firstOrCreate(['version' => 1], ['is_refundable' => true, 'refund_window_days' => 14, 'terms' => 't']);

        $preorder = Preorder::create([
            'landing_page_id' => $this->page->id,
            'contact_id' => $contact->id,
            'refund_policy_id' => $policy->id,
            'name' => $contact->name,
            'phone' => $contact->mobile,
            'amount_rial' => 1_200_000,
            'payment_status' => $status,
            'public_token' => (string) Str::uuid(),
        ]);

        $preorder->payments()->create(['amount_rial' => 1_200_000, 'status' => 'paid', 'authority' => 'A' . Str::random(10), 'ref_id' => '555', 'verified_at' => now()]);

        return $preorder;
    }

    private function dispatch(): array
    {
        return app(SmsDispatcher::class)->run();
    }

    private function admin(AdminRole $role = AdminRole::SuperAdmin): User
    {
        return User::factory()->create(['role' => $role]);
    }

    /**
     * مرحله ۱۱۷: لینک پیگیری فقط وقتی ارسال می‌شود که مدیر نام پارامتر آن را
     * وارد کرده باشد؛ قالب‌های فعلی SMS.ir بدون آن تغییری نمی‌کنند.
     */
    public function test_payment_sms_link_is_optional(): void
    {
        $this->enable('payment_paid');
        $template = SmsTemplate::where('key', 'payment_paid')->sole();
        $this->assertSame('', $template->parameters['link']);

        $this->actingAs($this->admin(), 'web')
            ->put(route('admin.sms.templates.update', $template), ['enabled' => '1', 'provider_template_id' => 100, 'parameters' => ['link' => 'LINK'] + $template->parameters])
            ->assertSessionHasNoErrors();

        app(\App\Services\Notifications\CustomerNotifier::class)->paymentPaid($this->paidPreorder()->payments()->sole());
        $this->dispatch();

        $this->assertSame(route('account.login'), SmsMessage::sole()->parameters['LINK']);
    }

    public function test_payment_success_notifies_once_after_server_verification(): void
    {
        $this->enable('payment_paid');

        $preorder = Preorder::create([
            'landing_page_id' => $this->page->id, 'contact_id' => $this->contact->id, 'name' => 'علی', 'phone' => '09121234567',
            'amount_rial' => 1_200_000, 'public_token' => (string) Str::uuid(),
        ]);
        $payment = $preorder->payments()->create(['amount_rial' => 1_200_000, 'status' => 'pending', 'authority' => 'A123']);

        // بازگشت ناموفق مرورگر پیامکی نمی‌سازد
        $this->get(route('payments.zarinpal.callback', ['Authority' => 'A123', 'Status' => 'NOK']));
        $this->assertSame(0, SmsMessage::count());

        Payment::whereKey($payment->id)->update(['status' => 'pending']);
        $this->get(route('payments.zarinpal.callback', ['Authority' => 'A123', 'Status' => 'OK']));
        $this->get(route('payments.zarinpal.callback', ['Authority' => 'A123', 'Status' => 'OK']));
        app(PreorderPaymentService::class)->verify($payment->fresh());

        $message = SmsMessage::sole();
        $this->assertSame('payment_paid', $message->template_key);
        $this->assertSame("payment.paid:{$payment->id}", $message->dedupe_key);
        $this->assertSame($this->contact->id, $message->contact_id);
        // پس از پاسخ (defer) ارسال شده است
        $this->assertSame('sent', $message->status);
        $this->assertSame('9001', $message->provider_message_id);
        $this->assertEquals(['NAME' => 'علی', 'IDEA' => 'باشگاه‌یار', 'AMOUNT' => '120,000', 'REF' => '777'], $message->parameters);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'send/verify')
            && $r['templateId'] === 100
            && $r['mobile'] === '09121234567');
    }

    public function test_disabled_template_creates_no_message(): void
    {
        $preorder = $this->paidPreorder();

        app(RefundService::class)->request($preorder, $preorder->payments()->first(), 500_000, 'دلیل', $this->contact);

        $this->assertSame(0, SmsMessage::count());
    }

    public function test_refund_stages_each_send_their_own_message(): void
    {
        $this->enable('refund_requested', 'refund_approved', 'refund_rejected', 'refund_executed');
        $admin = $this->admin(AdminRole::Finance);
        $service = app(RefundService::class);

        $preorder = $this->paidPreorder();
        $payment = $preorder->payments()->first();

        $request = $service->request($preorder, $payment, 1_200_000, 'دلیل', $this->contact);
        $service->approve($request, 800_000, null, $admin);
        $service->execute($request->fresh(), 300_000, 'bank_transfer', 'REF-1', now(), null, $admin);
        $service->execute($request->fresh(), 500_000, 'bank_transfer', 'REF-2', now(), null, $admin);

        $other = $this->paidPreorder(Contact::create(['mobile' => '09129999999', 'name' => 'سارا']));
        $service->reject($service->request($other, $other->payments()->first(), 100_000, 'x', $admin), 'خارج از شرایط', $admin);

        $this->dispatch();

        $messages = SmsMessage::orderBy('id')->get();
        $this->assertSame(
            ['refund_requested', 'refund_approved', 'refund_executed', 'refund_executed', 'refund_requested', 'refund_rejected'],
            $messages->pluck('template_key')->all()
        );
        $this->assertSame(['120,000', '80,000', '30,000', '50,000'], $messages->take(4)->map(fn ($m) => $m->variables['amount'])->all());
        $this->assertSame('REF-2', $messages[3]->variables['reference']);
        $this->assertSame('09129999999', $messages[5]->mobile);
        $this->assertSame(6, SmsMessage::where('status', 'sent')->count());
    }

    public function test_idea_status_sms_goes_only_to_paying_customers_when_requested(): void
    {
        $this->enable('idea_launched');
        $manager = $this->admin(AdminRole::Manager);

        $this->paidPreorder();
        $this->paidPreorder(); // سفارش دوم همان مخاطب: یک پیام
        $this->paidPreorder(Contact::create(['mobile' => '09125555555', 'name' => 'رضا']), 'partially_refunded');
        $this->paidPreorder(Contact::create(['mobile' => '09126666666', 'name' => 'مینا']), 'refunded');
        Preorder::create(['landing_page_id' => $this->page->id, 'name' => 'ن', 'phone' => '09127777777', 'amount_rial' => 1, 'payment_status' => 'unpaid', 'public_token' => (string) Str::uuid()]);

        $this->actingAs($manager, 'web')
            ->get(route('ideas.show', $this->idea))
            ->assertOk()
            ->assertSee('اطلاع پیامکی به 2 خریدار');

        $this->actingAs($manager, 'web')
            ->patch(route('ideas.status', $this->idea), ['status' => 'launched', 'notify_customers' => '1'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'پیامک برای 2 خریدار'));

        $this->assertEqualsCanonicalizing(['09121234567', '09125555555'], SmsMessage::pluck('mobile')->all());
        $this->assertSame(['idea_launched'], SmsMessage::distinct()->pluck('template_key')->all());
        $this->assertSame(2, SmsMessage::where('status', 'sent')->count());

        // بدون انتخاب مدیر یا بدون قالب فعال پیامکی ساخته نمی‌شود
        $this->actingAs($manager, 'web')->patch(route('ideas.status', $this->idea), ['status' => 'building']);
        $this->actingAs($manager, 'web')
            ->patch(route('ideas.status', $this->idea), ['status' => 'validated', 'notify_customers' => '1'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'پیامکی ارسال نشد'));
        $this->assertSame(2, SmsMessage::count());
    }

    /**
     * مرحله ۱۳۲: درخواست‌کنندگان «خبرم کن» هنگام عرضه با قالب جدا پیامک
     * می‌گیرند؛ خریداران فقط پیام خریدار را، و هر شماره فقط یک بار.
     */
    public function test_launch_notifies_notify_me_requesters_once_without_buyers(): void
    {
        $this->enable('idea_launched', 'idea_launched_notify');
        $manager = $this->admin(AdminRole::Manager);
        $lead = fn (string $phone, string $intent = 'notify_when_ready') => $this->page->leads()->create([
            'name' => 'درخواست‌کننده', 'phone' => $phone, 'status' => 'new', 'intent' => $intent, 'consented_at' => now(),
        ]);

        $this->paidPreorder();
        $lead('09121234567');               // خریدار هم «خبرم کن» زده: فقط پیام خریدار
        $lead('09123333333');
        $lead('09123333333');               // دو بار ثبت: یک پیام
        $lead('09124444444');
        $lead('09128888888', 'interest');   // ثبت علاقه قدیمی: پیامی نمی‌گیرد

        $this->actingAs($manager, 'web')->get(route('ideas.show', $this->idea))->assertSee('به 2 نفر که «خبرم کن» زده‌اند');

        $this->actingAs($manager, 'web')
            ->patch(route('ideas.status', $this->idea), ['status' => 'launched', 'notify_customers' => '1', 'notify_requesters' => '1'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'پیامک برای 1 خریدار') && str_contains($m, 'پیامک آمادگی برای 2 درخواست‌کننده'));

        $this->assertSame(['09121234567'], SmsMessage::where('template_key', 'idea_launched')->pluck('mobile')->all());
        $this->assertEqualsCanonicalizing(['09123333333', '09124444444'], SmsMessage::where('template_key', 'idea_launched_notify')->pluck('mobile')->all());

        // عرضه دوباره همان ایده پیام آمادگی تکراری نمی‌سازد
        $this->actingAs($manager, 'web')->patch(route('ideas.status', $this->idea), ['status' => 'building']);
        $this->actingAs($manager, 'web')->patch(route('ideas.status', $this->idea), ['status' => 'launched', 'notify_requesters' => '1']);
        $this->assertSame(2, SmsMessage::where('template_key', 'idea_launched_notify')->count());

        // قالب غیرفعال: پیامی ساخته نمی‌شود و مدیر مطلع می‌شود
        SmsTemplate::where('key', 'idea_launched_notify')->update(['enabled' => false]);
        $lead('09129999999');
        $this->actingAs($manager, 'web')->patch(route('ideas.status', $this->idea), ['status' => 'building']);
        $this->actingAs($manager, 'web')
            ->patch(route('ideas.status', $this->idea), ['status' => 'launched', 'notify_requesters' => '1'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'فعال نیست'));
        $this->assertSame(0, SmsMessage::where('mobile', '09129999999')->count());
    }

    public function test_transient_failures_retry_limited_times_and_rejections_do_not(): void
    {
        $this->enable('payment_paid');
        $sms = app(\App\Services\Sms\SmsService::class);

        $sms->queueTemplate('payment_paid', '09121234567', ['name' => 'a'], 'k1');
        $this->smsMode = 'down';

        $this->assertSame(1, $this->dispatch()['retry']);
        $message = SmsMessage::sole();
        $this->assertSame('queued', $message->status);
        $this->assertSame(1, $message->attempts);
        $this->assertTrue($message->available_at->isFuture());

        // قبل از زمان تلاش بعدی برداشته نمی‌شود
        $this->assertSame(0, $this->dispatch()['retry']);

        $this->travel(6)->minutes();
        $this->dispatch();
        $this->travel(11)->minutes();
        $this->assertSame(1, $this->dispatch()['failed']);
        $this->assertSame(['failed', 3], [$message->fresh()->status, $message->fresh()->attempts]);

        // رد قطعی: بدون تلاش دوباره
        $this->smsMode = 'reject';
        $sms->queueTemplate('payment_paid', '09121234567', ['name' => 'b'], 'k2');
        $this->assertSame(1, $this->dispatch()['failed']);
        $rejected = SmsMessage::where('dedupe_key', 'k2')->sole();
        $this->assertSame(1, $rejected->attempts);
        $this->assertStringContainsString('اعتبار کافی نیست', $rejected->error);
        $this->assertSame(0, $rejected->response['status']);

        // تلاش دوباره دستی مدیر
        $this->smsMode = 'ok';
        $this->actingAs($this->admin(AdminRole::Support), 'web')
            ->post(route('admin.sms.messages.retry', $rejected))
            ->assertSessionHas('success');
        $this->assertSame('sent', $rejected->fresh()->status);
    }

    public function test_dedupe_claim_rate_limit_and_stuck_messages(): void
    {
        $this->enable('payment_paid');
        $sms = app(\App\Services\Sms\SmsService::class);

        $this->assertNotNull($sms->queueTemplate('payment_paid', '09121234567', [], 'same'));
        $this->assertNull($sms->queueTemplate('payment_paid', '09121234567', [], 'same'));

        foreach (range(1, 4) as $i) {
            $sms->queueTemplate('payment_paid', '0912000000' . $i, [], "k{$i}");
        }

        config(['sms.rate_per_minute' => 3]);
        $this->assertSame(3, $this->dispatch()['sent']);
        $this->assertSame(0, $this->dispatch()['sent']);
        $this->travel(2)->minutes();
        $this->assertSame(2, $this->dispatch()['sent']);
        $this->assertSame(5, SmsMessage::where('status', 'sent')->count());
        Http::assertSentCount(5);

        // پیام برداشته‌شده توسط اجرای دیگر دوباره ارسال نمی‌شود و اگر گیر کند بسته می‌شود
        $stuck = $sms->queueTemplate('payment_paid', '09121111111', [], 'stuck');
        $stuck->update(['status' => 'sending']);
        $this->dispatch();
        $this->assertSame('sending', $stuck->fresh()->status);
        $this->travel(20)->minutes();
        $this->dispatch();
        $this->assertSame('failed', $stuck->fresh()->status);
        Http::assertSentCount(5);
    }

    public function test_broadcast_preview_confirmation_and_bulk_sending(): void
    {
        $admin = $this->admin(AdminRole::Manager);
        $this->contact->setMarketingConsent(true, 'test');
        $this->contact->interests()->attach($this->idea->id, ['source' => 'lead']);

        $make = function (string $mobile, bool $consent, bool $interested = true) {
            $contact = Contact::create(['mobile' => $mobile]);
            if ($consent) {
                $contact->setMarketingConsent(true, 'test');
            }
            if ($interested) {
                $contact->interests()->attach($this->idea->id, ['source' => 'lead']);
            }

            return $contact;
        };

        $make('09120000001', true);
        $make('09120000002', false);
        $recent = $make('09120000003', true);
        $make('09120000004', true, false); // علاقه‌مند نیست
        SmsMessage::create(['mobile' => $recent->mobile, 'contact_id' => $recent->id, 'type' => 'text', 'category' => 'promotional', 'provider' => 'smsir', 'status' => 'sent', 'created_at' => now()->subHours(3)]);

        $this->actingAs($admin, 'web')
            ->post(route('admin.sms.broadcasts.store'), [
                'title' => 'معرفی', 'body' => 'باشگاه‌یار عرضه شد.', 'audience' => 'rules',
                'rules' => ['ideas' => [$this->idea->id]], 'frequency_cap_hours' => 24,
            ])->assertRedirect();

        $broadcast = SmsBroadcast::sole();
        $this->assertSame('draft', $broadcast->status);

        $this->actingAs($admin, 'web')
            ->get(route('admin.sms.broadcasts.show', $broadcast))
            ->assertOk()
            ->assertSee('لغو۱۱')
            ->assertSeeInOrder(['مخاطبان منطبق', '4', 'بدون رضایت', '1', 'پیامک تبلیغاتی اخیر', '1', 'گیرنده نهایی', '2']);
        $this->assertSame(0, SmsMessage::where('broadcast_id', $broadcast->id)->count());

        // تعداد تغییرکرده یا بدون تأیید صریح: ارسال نمی‌شود
        $this->actingAs($admin, 'web')->post(route('admin.sms.broadcasts.confirm', $broadcast), ['expected' => 2])->assertSessionHasErrors('confirm');
        $this->actingAs($admin, 'web')->post(route('admin.sms.broadcasts.confirm', $broadcast), ['expected' => 3, 'confirm' => '1'])->assertSessionHasErrors('broadcast');
        $this->assertSame('draft', $broadcast->fresh()->status);

        $this->actingAs($admin, 'web')
            ->post(route('admin.sms.broadcasts.confirm', $broadcast), ['expected' => 2, 'confirm' => '1'])
            ->assertSessionHas('success');

        $broadcast->refresh();
        $this->assertSame('completed', $broadcast->status);
        $this->assertSame(2, $broadcast->recipients_count);
        $this->assertEqualsCanonicalizing(['09121234567', '09120000001'], $broadcast->messages()->where('status', 'sent')->pluck('mobile')->all());
        $this->assertSame("باشگاه‌یار عرضه شد.\nلغو۱۱", $broadcast->messages()->first()->body);
        $this->assertSame(1, AuditLog::where('action', 'sms.broadcast_confirmed')->count());

        Http::assertSent(fn ($r) => str_ends_with($r->url(), 'send/bulk')
            && $r['lineNumber'] === 300012345
            && count($r['mobiles']) === 2
            && str_ends_with($r['messageText'], 'لغو۱۱'));

        // تأیید دوباره همان ارسال ممکن نیست
        $this->actingAs($admin, 'web')->post(route('admin.sms.broadcasts.confirm', $broadcast), ['expected' => 2, 'confirm' => '1'])->assertSessionHasErrors('broadcast');
        $this->assertSame(2, SmsMessage::where('broadcast_id', $broadcast->id)->count());
    }

    public function test_broadcast_respects_promo_hours_consent_changes_and_cancel(): void
    {
        $admin = $this->admin();
        $group = ContactGroup::create(['name' => 'vip', 'type' => 'manual']);

        $contacts = collect(range(1, 3))->map(function ($i) use ($group) {
            $contact = Contact::create(['mobile' => '0912000000' . $i]);
            $contact->setMarketingConsent(true, 'test');
            $group->manualMembers()->attach($contact->id);

            return $contact;
        });

        $broadcast = SmsBroadcast::create(['title' => 't', 'body' => 'سلام', 'audience' => 'group', 'contact_group_id' => $group->id, 'frequency_cap_hours' => 0, 'created_by' => $admin->id]);

        // ۲۳:۰۰ تهران: خارج از ساعات مجاز
        $this->travelTo(Carbon::parse('2026-10-29 19:30:00', 'UTC'));
        $this->assertSame(3, app(BroadcastService::class)->confirm($broadcast, 3, $admin));
        $this->dispatch();
        $this->assertSame(3, SmsMessage::where('status', 'queued')->count());

        // لغو رضایت پس از ثبت در صف
        $contacts[0]->setMarketingConsent(false, 'account');

        $this->travelTo(Carbon::parse('2026-10-30 06:00:00', 'UTC'));
        config(['sms.batch_size' => 2]);
        $stats = $this->dispatch();
        $this->assertSame(['sent' => 1, 'skipped' => 1], ['sent' => $stats['sent'], 'skipped' => $stats['skipped']]);
        $this->assertSame('sending', $broadcast->fresh()->status);

        $this->actingAs($admin, 'web')
            ->post(route('admin.sms.broadcasts.cancel', $broadcast))
            ->assertSessionHas('success');

        $this->assertSame(
            ['cancelled' => 1, 'sent' => 1, 'skipped' => 1],
            SmsMessage::selectRaw('status, COUNT(*) AS c')->groupBy('status')->orderBy('status')->pluck('c', 'status')->map(fn ($c) => (int) $c)->all()
        );
        $this->assertSame('cancelled', $broadcast->fresh()->status);
        $this->dispatch();
        $this->assertSame(1, SmsMessage::where('status', 'sent')->count());
    }

    public function test_manual_send_permissions_and_template_management(): void
    {
        $support = $this->admin(AdminRole::Support);
        $finance = $this->admin(AdminRole::Finance);

        $this->actingAs($support, 'web')->get(route('admin.sms.broadcasts.index'))->assertForbidden();
        $this->actingAs($finance, 'web')->get(route('admin.sms.messages.index'))->assertForbidden();
        $this->actingAs($finance, 'web')->post(route('admin.contacts.sms', $this->contact), ['body' => 'x'])->assertForbidden();

        $this->actingAs($support, 'web')
            ->post(route('admin.contacts.sms', $this->contact), ['body' => 'سفارش شما در حال آماده‌سازی است.'])
            ->assertSessionHas('success');

        $message = SmsMessage::sole();
        $this->assertSame(['text', 'transactional', 'sent', $support->id, $this->contact->id], [$message->type, $message->category, $message->status, $message->user_id, $message->contact_id]);
        $this->assertSame(1, AuditLog::where('action', 'sms.manual_sent')->count());

        $this->actingAs($support, 'web')->get(route('admin.sms.messages.index', ['mobile' => '09121234567']))
            ->assertOk()
            ->assertSee('سفارش شما در حال آماده‌سازی است.');
        $this->actingAs($support, 'web')->get(route('admin.contacts.show', $this->contact))->assertOk()->assertSee('ارسال پیامک');

        // قالب: فعال‌سازی بدون شناسه ممکن نیست؛ پیش‌نمایش متن
        $admin = $this->admin();
        $template = SmsTemplate::where('key', 'payment_paid')->sole();

        $this->actingAs($admin, 'web')
            ->put(route('admin.sms.templates.update', $template), ['enabled' => '1', 'parameters' => $template->parameters])
            ->assertSessionHasErrors('provider_template_id');

        $this->actingAs($admin, 'web')
            ->put(route('admin.sms.templates.update', $template), ['enabled' => '1', 'provider_template_id' => 5555, 'body' => 'سلام #NAME#', 'parameters' => $template->parameters])
            ->assertRedirect(route('admin.sms.index'));
        $this->assertTrue($template->fresh()->enabled);

        $this->actingAs($admin, 'web')->get(route('admin.sms.templates.edit', $template))->assertOk()->assertSee('سلام سارا');
        $this->actingAs($admin, 'web')->get(route('admin.sms.index'))->assertOk()->assertSee('قالب‌های تراکنشی');

        // قالب کد ورود همیشه فعال می‌ماند
        $otp = SmsTemplate::where('key', 'otp')->sole();
        $this->actingAs($admin, 'web')->put(route('admin.sms.templates.update', $otp), ['provider_template_id' => 1, 'parameters' => $otp->parameters]);
        $this->assertTrue($otp->fresh()->enabled);
    }
}

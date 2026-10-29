<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Contact;
use App\Models\Idea;
use App\Models\OtpCode;
use App\Models\Preorder;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\Otp\OtpException;
use App\Services\Otp\OtpService;
use App\Services\Sms\LogSmsProvider;
use App\Services\Sms\SmsException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ContactOtpTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE = '09121112233';

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');

        config([
            'services.sms.driver' => 'smsir',
            'services.sms.smsir.api_key' => 'test-key',
        ]);

        SmsTemplate::where('key', 'otp')->update(['provider_template_id' => 123456]);

        Http::fake(fn () => Http::response($this->smsResponse, $this->smsStatus));
    }

    private array $smsResponse = ['status' => 1, 'message' => 'موفق', 'data' => ['messageId' => 777, 'cost' => 1.5]];

    private int $smsStatus = 200;

    private function lastSentCode(): string
    {
        $request = Http::recorded()->last()[0];

        return $request['parameters'][0]['value'];
    }

    private function requestCode(string $mobile = '۰۹۱۲۱۱۱۲۲۳۳')
    {
        return $this->post(route('account.login.send'), ['mobile' => $mobile]);
    }

    private function login(): Contact
    {
        $this->requestCode()->assertRedirect(route('account.verify'));
        $this->post(route('account.verify.submit'), ['code' => $this->lastSentCode()])
            ->assertRedirect(route('account.dashboard'));

        return Contact::where('mobile', self::MOBILE)->sole();
    }

    public function test_full_login_creates_contact_without_admin_access(): void
    {
        $contact = $this->login();

        $this->assertAuthenticatedAs($contact, 'contact');
        $this->assertGuest('web');
        $this->assertNotNull($contact->last_login_at);

        Http::assertSent(fn ($request) => $request->hasHeader('X-API-KEY', 'test-key')
            && $request['mobile'] === self::MOBILE
            && $request['templateId'] === 123456
            && $request['parameters'][0]['name'] === 'CODE');

        $this->get(route('account.dashboard'))->assertOk()->assertSee(self::MOBILE);
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_code_is_never_stored_in_plain_text(): void
    {
        $this->requestCode();
        $code = $this->lastSentCode();

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertNotSame($code, OtpCode::sole()->code_hash);
        $this->assertStringNotContainsString($code, OtpCode::sole()->code_hash);

        $message = SmsMessage::sole();
        $this->assertSame('sent', $message->status);
        $this->assertSame('777', $message->provider_message_id);
        $this->assertSame(['CODE' => '***'], $message->parameters);
    }

    public function test_resend_cooldown_and_previous_code_invalidation(): void
    {
        $this->requestCode();
        $first = $this->lastSentCode();

        $this->requestCode()->assertSessionHasErrors('code');
        $this->assertSame(1, OtpCode::count());

        $this->travel(61)->seconds();
        $this->requestCode()->assertRedirect(route('account.verify'));
        $second = $this->lastSentCode();

        if ($first !== $second) {
            $this->post(route('account.verify.submit'), ['code' => $first])->assertSessionHasErrors('code');
        }

        $this->post(route('account.verify.submit'), ['code' => $second])->assertRedirect(route('account.dashboard'));
    }

    public function test_hourly_send_limit_per_mobile(): void
    {
        foreach (range(1, 5) as $i) {
            $this->requestCode()->assertRedirect(route('account.verify'));
            $this->travel(61)->seconds();
        }

        $this->requestCode()->assertSessionHasErrors();
        $this->assertSame(5, OtpCode::count());
    }

    public function test_attempt_limit_locks_the_code(): void
    {
        $this->requestCode();
        $code = $this->lastSentCode();
        $wrong = $code === '000000' ? '111111' : '000000';

        foreach (range(1, 4) as $remaining) {
            $this->post(route('account.verify.submit'), ['code' => $wrong])
                ->assertSessionHasErrors(['code' => 'کد واردشده درست نیست. ' . (5 - $remaining) . ' تلاش دیگر باقی مانده است.']);
        }

        $this->post(route('account.verify.submit'), ['code' => $wrong])
            ->assertSessionHasErrors(['code' => 'تعداد تلاش‌های ناموفق زیاد بود؛ کد جدید دریافت کنید.']);

        $this->post(route('account.verify.submit'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('contact');
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->requestCode();
        $code = $this->lastSentCode();

        $this->travel(121)->seconds();

        $this->post(route('account.verify.submit'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest('contact');
    }

    public function test_code_cannot_be_reused(): void
    {
        $otp = app(OtpService::class);
        $otp->send(self::MOBILE, '127.0.0.1');
        $code = $this->lastSentCode();

        $otp->verify(self::MOBILE, $code);

        $this->expectException(OtpException::class);
        $otp->verify(self::MOBILE, $code);
    }

    public function test_persian_digit_code_is_accepted(): void
    {
        $this->requestCode();
        $persian = strtr($this->lastSentCode(), ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);

        $this->post(route('account.verify.submit'), ['code' => $persian])->assertRedirect(route('account.dashboard'));
    }

    public function test_unconfigured_template_fails_safely(): void
    {
        SmsTemplate::where('key', 'otp')->update(['provider_template_id' => null]);

        $this->requestCode()->assertSessionHasErrors('mobile');

        Http::assertNothingSent();
        $this->assertSame('failed', SmsMessage::sole()->status);
        $this->assertNotNull(OtpCode::sole()->consumed_at);
    }

    public function test_provider_rejection_is_logged(): void
    {
        $this->smsResponse = ['status' => 0, 'message' => 'اعتبار کافی نیست'];
        $this->smsStatus = 400;

        $this->requestCode()->assertSessionHasErrors('mobile');

        $this->assertStringContainsString('اعتبار کافی نیست', SmsMessage::sole()->error);
    }

    public function test_log_driver_is_refused_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->expectException(SmsException::class);
        (new LogSmsProvider())->sendTemplate(self::MOBILE, 1, ['CODE' => '123456']);
    }

    public function test_account_shows_only_own_orders_and_existing_history(): void
    {
        $idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'testing']);
        $page = $idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't', 'is_active' => true]);

        // سوابق قبل از ورود با همین شماره (قالب متفاوت)
        $this->post(route('leads.store', 'p'), ['name' => 'علی', 'phone' => '+98 912 111 2233', 'consent' => '1']);

        $other = Contact::create(['mobile' => '09129998877']);
        Preorder::create([
            'landing_page_id' => $page->id,
            'contact_id' => $other->id,
            'name' => 'دیگری',
            'phone' => '09129998877',
            'amount_rial' => 1_000_000,
            'public_token' => (string) Str::uuid(),
        ]);
        Preorder::create([
            'landing_page_id' => $page->id,
            'contact_id' => Contact::where('mobile', self::MOBILE)->value('id'),
            'name' => 'علی',
            'phone' => self::MOBILE,
            'amount_rial' => 2_000_000,
            'public_token' => $myToken = (string) Str::uuid(),
        ]);

        $contact = $this->login();
        $this->assertSame('علی', $contact->name);

        $this->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('باشگاه‌یار')
            ->assertSee('200,000 تومان')
            ->assertSee($myToken)
            ->assertDontSee('100,000 تومان');
    }

    public function test_marketing_consent_is_separate_and_revocable(): void
    {
        $idea = Idea::create(['name' => 'i', 'slug' => 'i', 'status' => 'testing']);
        $idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't', 'is_active' => true]);

        $this->post(route('leads.store', 'p'), ['name' => 'a', 'phone' => self::MOBILE, 'consent' => '1']);
        $this->assertFalse(Contact::sole()->marketing_sms_consent);

        $this->post(route('leads.store', 'p'), ['name' => 'a', 'phone' => self::MOBILE, 'consent' => '1', 'marketing_consent' => '1']);
        $contact = Contact::sole();
        $this->assertTrue($contact->marketing_sms_consent);
        $this->assertSame('lead_form:p', $contact->marketing_consent_source);

        // فرم بعدی بدون تیک، رضایت قبلی را لغو نمی‌کند
        $this->post(route('leads.store', 'p'), ['name' => 'a', 'phone' => self::MOBILE, 'consent' => '1']);
        $this->assertTrue(Contact::sole()->marketing_sms_consent);

        $this->login();
        $this->put(route('account.consent'), ['marketing_sms_consent' => '0'])->assertRedirect();

        $contact = Contact::sole();
        $this->assertFalse($contact->marketing_sms_consent);
        $this->assertNotNull($contact->marketing_opted_out_at);
    }

    public function test_contact_session_has_absolute_lifetime(): void
    {
        $this->login();

        $this->travel(25)->hours();

        $this->get(route('account.dashboard'))->assertRedirect(route('account.login'));
        $this->assertGuest('contact');
    }

    public function test_contact_logout_keeps_admin_session(): void
    {
        $admin = User::factory()->create(['role' => AdminRole::SuperAdmin]);
        $this->actingAs($admin);

        $this->login();
        $this->post(route('account.logout'))->assertRedirect(route('account.login'));

        $this->assertGuest('contact');
        $this->assertAuthenticatedAs($admin, 'web');
    }

    public function test_guest_is_sent_to_contact_login(): void
    {
        $this->get(route('account.dashboard'))->assertRedirect(route('account.login'));
    }

    public function test_admin_can_configure_sms_template_only_with_settings_permission(): void
    {
        $template = SmsTemplate::where('key', 'otp')->sole();

        $this->actingAs(User::factory()->create(['role' => AdminRole::Manager]))
            ->get(route('admin.sms.index'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => AdminRole::SuperAdmin]))
            ->put(route('admin.sms.templates.update', $template), [
                'provider_template_id' => '654321',
                'parameters' => ['code' => 'Code'],
            ])->assertRedirect(route('admin.sms.index'));

        $template->refresh();
        $this->assertSame(654321, $template->provider_template_id);
        $this->assertSame(['code' => 'Code'], $template->parameters);

        $this->put(route('admin.sms.templates.update', $template), [
            'parameters' => ['code' => 'bad name!'],
        ])->assertSessionHasErrors('parameters.code');

        $this->get(route('admin.sms.index'))->assertOk()->assertSee('654321');
    }
}

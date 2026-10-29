<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Services\SystemStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    private function check(string $label): array
    {
        return collect(app(SystemStatus::class)->checks())->firstWhere('label', $label);
    }

    public function test_page_reports_configuration_without_revealing_secrets(): void
    {
        config([
            'services.sms.driver' => 'smsir',
            'services.sms.smsir.api_key' => 'SECRET-SMS-KEY-123',
            'services.zarinpal.merchant_id' => 'SECRET-MERCHANT-456',
            'services.zarinpal.sandbox' => false,
        ]);
        SmsTemplate::where('key', 'otp')->update(['provider_template_id' => 123456]);

        $this->actingAs(User::factory()->create(['role' => AdminRole::SuperAdmin]))
            ->get(route('admin.system'))
            ->assertOk()
            ->assertSee('وضعیت سیستم')
            ->assertSee('شناسه 123456')
            ->assertDontSee('SECRET-SMS-KEY-123')
            ->assertDontSee('SECRET-MERCHANT-456')
            ->assertDontSee((string) config('app.key'));

        $this->assertSame('ok', $this->check('کلید API')['status']);
        $this->assertSame('ok', $this->check('حالت آزمایشی (sandbox)')['status']);
        $this->assertSame('ok', $this->check('migrationها')['status']);

        $this->actingAs(User::factory()->create(['role' => AdminRole::Manager]))->get(route('admin.system'))->assertForbidden();
    }

    public function test_production_misconfiguration_is_flagged(): void
    {
        $this->app['env'] = 'production';
        config([
            'app.debug' => true,
            'app.url' => 'http://example.com',
            'services.sms.driver' => 'log',
            'services.sms.smsir.api_key' => null,
            'services.zarinpal.merchant_id' => null,
            'services.zarinpal.sandbox' => true,
        ]);

        foreach (['حالت اشکال‌زدایی', 'نشانی سایت', 'ارسال‌کننده', 'کلید API', 'قالب کد ورود', 'شناسه پذیرنده زرین‌پال', 'حالت آزمایشی (sandbox)'] as $label) {
            $this->assertSame('error', $this->check($label)['status'], $label);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\User;
use App\Services\SiteSettings;
use App\Support\ContactChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * تنظیمات سایت و پاورقی اعتمادساز (مراحل ۱۱۱ تا ۱۱۸).
 */
class SiteSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->create(['role' => AdminRole::SuperAdmin]);
    }

    private function validSettings(array $overrides = []): array
    {
        return array_replace([
            'company_name' => 'ایده‌پردازان',
            'company_website' => 'https://itmb.ir',
            'company_intro' => 'پرداخت و پشتیبانی با شرکت ایده‌پردازان است.',
            'enamad_id' => '123456',
            'enamad_code' => 'AbCd1234',
            'support_help_text' => 'سؤال دارید؟ پیام دهید.',
            'channels' => [
                ['type' => 'bale', 'value' => '09121234567', 'label' => ''],
                ['type' => 'whatsapp', 'value' => '۰۹۱۲۱۲۳۴۵۶۷', 'label' => 'واتساپ فروش'],
                ['type' => '', 'value' => '', 'label' => ''],
            ],
        ], $overrides);
    }

    public function test_only_super_admin_manages_site_settings(): void
    {
        $manager = User::factory()->create(['role' => AdminRole::Manager]);

        $this->actingAs($manager)->get(route('admin.settings.site'))->assertForbidden();
        $this->actingAs($manager)->put(route('admin.settings.site.update'), $this->validSettings())->assertForbidden();
        $this->actingAs($this->owner())->get(route('admin.settings.site'))->assertOk()->assertSee('نماد اعتماد الکترونیکی');
    }

    public function test_settings_are_saved_with_logo_and_normalized_channels(): void
    {
        Storage::fake('uploads');
        $owner = $this->owner();

        $this->actingAs($owner)
            ->put(route('admin.settings.site.update'), $this->validSettings(['company_logo' => UploadedFile::fake()->image('logo.png', 200, 80)]))
            ->assertRedirect(route('admin.settings.site'))
            ->assertSessionHas('success');

        $settings = app(SiteSettings::class);
        $this->assertSame('ایده‌پردازان', $settings->companyName());
        Storage::disk('uploads')->assertExists($settings->get('company')['logo']);
        $this->assertCount(2, $settings->supportChannels());
        $this->assertSame('https://wa.me/989121234567', $settings->supportChannels()[1]->url());
        $this->assertSame('https://trustseal.enamad.ir/?id=123456&Code=AbCd1234', $settings->enamad()['url']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'site_settings.updated']);

        // حذف لوگو فایل را هم پاک می‌کند
        $logo = $settings->get('company')['logo'];
        $this->actingAs($owner)->put(route('admin.settings.site.update'), $this->validSettings(['remove_logo' => '1']));
        Storage::disk('uploads')->assertMissing($logo);
        $this->assertNull(app(SiteSettings::class)->logoUrl());
    }

    public function test_invalid_values_are_rejected(): void
    {
        $owner = $this->owner();

        $this->actingAs($owner)
            ->put(route('admin.settings.site.update'), $this->validSettings([
                'enamad_id' => '12ab',
                'company_website' => 'javascript:alert(1)',
                'channels' => [['type' => 'whatsapp', 'value' => 'not-a-number', 'label' => '']],
            ]))
            ->assertSessionHasErrors(['enamad_id', 'company_website']);

        $this->actingAs($owner)
            ->put(route('admin.settings.site.update'), $this->validSettings(['channels' => [['type' => 'whatsapp', 'value' => 'not-a-number', 'label' => '']]]))
            ->assertSessionHasErrors('channels.0.value');

        $this->actingAs($owner)
            ->put(route('admin.settings.site.update'), $this->validSettings(['enamad_code' => '']))
            ->assertSessionHasErrors('enamad_code');

        $this->assertNull(app(SiteSettings::class)->companyName());
    }

    public function test_contact_channel_links(): void
    {
        $url = fn (string $type, string $value) => ContactChannel::fromArray(['type' => $type, 'value' => $value])?->url();

        $this->assertSame('https://wa.me/989121234567', $url('whatsapp', '+98 912 123 4567'));
        $this->assertSame('https://t.me/itmb_support', $url('telegram', '@itmb_support'));
        $this->assertSame('https://t.me/+989121234567', $url('telegram', '09121234567'));
        $this->assertSame('https://ble.ir/itmb_support', $url('bale', 'itmb_support'));
        $this->assertSame('https://eitaa.com/itmb_support', $url('eitaa', 'itmb_support'));
        $this->assertNull($url('bale', '09121234567'));
        $this->assertSame('tel:+982112345678', $url('phone', '021-1234-5678'));
        $this->assertSame('mailto:support@itmb.ir', $url('email', 'support@itmb.ir'));

        // واتساپ: شناسه، لینک رسمی و شماره خارجی
        $this->assertNotNull(ContactChannel::fromArray(['type' => 'whatsapp', 'value' => 'itmb_support']));
        $this->assertNull($url('whatsapp', 'itmb_support'));
        $this->assertSame('@itmb_support', ContactChannel::fromArray(['type' => 'whatsapp', 'value' => 'itmb_support'])->display());
        $this->assertSame('https://wa.me/message/ABCD12', $url('whatsapp', 'wa.me/message/ABCD12'));
        $this->assertSame('https://chat.whatsapp.com/Invite123', $url('whatsapp', 'https://chat.whatsapp.com/Invite123'));
        $this->assertSame('https://wa.me/971501234567', $url('whatsapp', '+971 50 123 4567'));
        $this->assertSame('https://t.me/itmb', $url('telegram', 'https://t.me/itmb'));
        $this->assertSame('https://ble.ir/itmb', $url('bale', 'ble.ir/itmb'));
        // لینک خودش نمایش داده نمی‌شود: متن نمایشی، یا شناسه/شماره قابل استخراج، یا هیچ
        $channel = fn (array $data) => ContactChannel::fromArray($data);
        $this->assertSame('@itmb_support', $channel(['type' => 'whatsapp', 'value' => 'wa.me/message/ABCD12', 'display' => '@itmb_support'])->display());
        $this->assertSame('https://wa.me/message/ABCD12', $channel(['type' => 'whatsapp', 'value' => 'wa.me/message/ABCD12', 'display' => '@itmb_support'])->url());
        $this->assertSame('', $channel(['type' => 'whatsapp', 'value' => 'wa.me/message/ABCD12'])->display());
        $this->assertSame('09121234567', $channel(['type' => 'whatsapp', 'value' => 'https://wa.me/989121234567'])->display());
        $this->assertSame('@itmb', $channel(['type' => 'telegram', 'value' => 'https://t.me/itmb'])->display());

        // لینک دامنه دیگر یا نشانی دست‌کاری‌شده پذیرفته نمی‌شود
        foreach (['https://evil.com/wa.me', 'wa.me.evil.com/x', 'https://user@wa.me/x', 'wa.me/x"onmouseover=y', 'javascript:alert(1)', 'wa.me/'] as $bad) {
            $this->assertNull(ContactChannel::fromArray(['type' => 'whatsapp', 'value' => $bad]), $bad);
        }

        $this->assertNull(ContactChannel::fromArray(['type' => 'telegram', 'value' => 'https://evil.example']));
        $this->assertNull(ContactChannel::fromArray(['type' => 'sms', 'value' => '09121234567']));
        $this->assertSame('09121234567', ContactChannel::fromArray(['type' => 'bale', 'value' => '۰۹۱۲۱۲۳۴۵۶۷'])->display());
    }

    public function test_whatsapp_link_shows_display_text_not_the_link(): void
    {
        $this->actingAs($this->owner())->put(route('admin.settings.site.update'), $this->validSettings(['channels' => [
            ['type' => 'whatsapp', 'value' => 'https://wa.me/message/ABCD12', 'label' => '', 'display' => '@itmb_support'],
            ['type' => 'whatsapp', 'value' => 'chat.whatsapp.com/Invite123', 'label' => 'گروه مشتریان', 'display' => ''],
        ]]))->assertSessionHasNoErrors();

        // فاصله‌های اضافه میان ویژگی‌های HTML اهمیتی ندارد
        $html = preg_replace('/\s+/', ' ', $this->get(route('account.login'))->assertOk()->getContent());
        $html = str_replace('" >', '">', $html);

        $this->assertStringContainsString('<a href="https://wa.me/message/ABCD12" target="_blank" rel="noopener">واتساپ: <bdi dir="ltr">@itmb_support</bdi></a>', $html);
        $this->assertStringContainsString('<a href="https://chat.whatsapp.com/Invite123" target="_blank" rel="noopener">گروه مشتریان</a>', $html);
        // متن پیوند هرگز خود نشانی نیست
        $this->assertStringNotContainsString('>wa.me', $html);
        $this->assertStringNotContainsString('chat.whatsapp.com/Invite123</bdi>', $html);
    }

    public function test_public_pages_show_trust_footer(): void
    {
        $idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'club', 'status' => 'testing']);
        $idea->landingPages()->create(['name' => 'p', 'slug' => 'club', 'title' => 'باشگاه‌یار', 'is_active' => true]);

        // بدون تنظیمات: فقط دکمه پیگیری، بدون متن «سیستم اعتبارسنجی»
        $this->get(route('landing-pages.show', 'club'))
            ->assertOk()
            ->assertDontSee('اعتبارسنجی')
            ->assertSee('پیگیری سفارش و ورود')
            ->assertSee(route('account.login'), false);

        $this->actingAs($this->owner())->put(route('admin.settings.site.update'), $this->validSettings(['company_name' => 'ایده‌پردازان <b>x</b>']));

        foreach ([route('landing-pages.show', 'club'), route('account.login'), route('payments.result', ['status' => 'error'])] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('ایده‌پردازان &lt;b&gt;x&lt;/b&gt;', false)
                ->assertSee('href="https://itmb.ir"', false)
                ->assertSee('https://trustseal.enamad.ir/logo.aspx?id=123456&amp;Code=AbCd1234', false)
                ->assertSee('href="https://wa.me/989121234567"', false)
                ->assertSee('واتساپ فروش')
                ->assertSee('سؤال دارید؟ پیام دهید.');
        }

        // روی صفحه ورود، دکمه پیگیری پاورقی تکرار نمی‌شود
        $this->get(route('account.login'))->assertDontSee('class="sf-track"', false);
        $this->get(route('account.login'))->assertSee('<title>پیگیری سفارش و ورود | ایده‌پردازان &lt;b&gt;x&lt;/b&gt;</title>', false);
    }
}

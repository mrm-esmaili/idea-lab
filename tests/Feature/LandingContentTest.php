<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\LandingPageEvent;
use App\Models\User;
use App\Services\FunnelStats;
use App\Services\SiteSettings;
use App\Support\Format;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * محتوای غنی صفحه فرود (مراحل ۱۱۹ تا ۱۲۸).
 */
class LandingContentTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');

        $this->actingAs(User::factory()->create(['role' => AdminRole::Manager]));

        $this->idea = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'clubyar', 'status' => 'testing', 'preorder_amount_rial' => 1_200_000]);
        $this->idea->refundPolicies()->create(['version' => 1, 'is_refundable' => true, 'refund_window_days' => 14, 'terms' => 'تا ۱۴ روز']);
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'صفحه',
            'slug' => 'club',
            'title' => 'باشگاهت را هوشمند مدیریت کن',
            'cta_text' => 'پیش‌خرید',
            'show_lead_form' => '1',
            'show_preorder_form' => '1',
            'features' => [
                ['title' => 'مدیریت شاگردان', 'description' => 'ثبت ۲۰۰ شاگرد در 5 دقیقه'],
                ['title' => 'گزارش مالی', 'description' => ''],
                ['title' => '', 'description' => ''],
            ],
            'demo_url' => 'https://demo.example.ir/club',
            'demo_label' => 'دیدن دمو',
            'demo_new_tab' => '1',
            'video_aparat' => 'https://www.aparat.com/v/Xy12Ab/معرفی',
            'preorder_title' => 'پیش‌خرید ویژه',
            'preorder_badge' => '50% تخفیف پیش‌خرید',
            'preorder_original_price_toman' => '۲۴۰٬۰۰۰',
            'preorder_includes' => "6 ماه اشتراک\n\nنصب رایگان",
            'preorder_guarantee' => 'ضمانت بازگشت کامل وجه',
            'preorder_button' => 'رزرو با تخفیف',
            'support_text' => 'سؤال دارید؟',
            'support_channels' => [['type' => 'telegram', 'value' => '@club_support', 'label' => '']],
            'show_trust' => '1',
        ], $overrides);
    }

    public function test_rich_content_is_saved_and_rendered(): void
    {
        $this->post(route('landing-pages.store', $this->idea), $this->payload())->assertSessionHasNoErrors();

        $content = LandingPage::sole()->contentData();
        $this->assertEquals([['title' => 'مدیریت شاگردان', 'description' => 'ثبت ۲۰۰ شاگرد در 5 دقیقه'], ['title' => 'گزارش مالی', 'description' => null]], $content['features']);
        $this->assertSame('Xy12Ab', $content['video_aparat']);
        $this->assertSame(240000, $content['preorder_original_price_toman']);
        $this->assertSame(['6 ماه اشتراک', 'نصب رایگان'], $content['preorder_includes']);

        app(SiteSettings::class)->save('company', ['name' => 'ایده‌پردازان', 'website' => 'https://itmb.ir'], null);
        app(SiteSettings::class)->save('support', ['help_text' => 'متن سایت', 'channels' => [['type' => 'phone', 'value' => '02112345678']]], null);

        $this->get('/l/club')
            ->assertOk()
            ->assertSee('<h3>مدیریت شاگردان</h3>', false)
            ->assertSee('ثبت ۲۰۰ شاگرد در ۵ دقیقه')
            ->assertSee('href="https://demo.example.ir/club" class="cta cta-secondary" data-track="demo_click"', false)
            ->assertSee('data-video-src="https://www.aparat.com/video/video/embed/videohash/Xy12Ab/vt/frame"', false)
            ->assertSee('۵۰٪ تخفیف پیش‌خرید')
            ->assertSee('<h2>پیش‌خرید ویژه</h2>', false)
            ->assertSee('۱۲۰٬۰۰۰ تومان')
            ->assertSee('<del class="price-old" aria-label="قیمت اصلی">۲۴۰٬۰۰۰ تومان</del>', false)
            ->assertSee('<li>۶ ماه اشتراک</li>', false)
            ->assertSee('ضمانت بازگشت کامل وجه')
            ->assertSee('رزرو با تخفیف')
            // راه تماس خود صفحه جای راه‌های سایت را می‌گیرد و پاورقی تکرارش نمی‌کند
            ->assertSee('href="https://t.me/club_support"', false)
            ->assertSee('سؤال دارید؟')
            ->assertDontSee('tel:+982112345678', false)
            ->assertDontSee('sf-support-title', false)
            ->assertSee('این محصول توسط')
            ->assertSee('ایده‌پردازان')
            // شرکت در بدنه صفحه آمده و پاورقی تکرارش نمی‌کند
            ->assertDontSee('ارائه‌شده توسط');
    }

    public function test_site_support_is_default_and_sections_are_optional(): void
    {
        app(SiteSettings::class)->save('support', ['help_text' => 'متن سایت', 'channels' => [['type' => 'phone', 'value' => '02112345678']]], null);
        app(SiteSettings::class)->save('company', ['name' => 'ایده‌پردازان'], null);

        $this->post(route('landing-pages.store', $this->idea), $this->payload([
            'support_text' => '', 'support_channels' => [], 'show_trust' => '0', 'demo_url' => '', 'video_aparat' => '',
            'preorder_original_price_toman' => '100,000',
        ]))->assertSessionHasNoErrors();

        $this->get('/l/club')
            ->assertSee('href="tel:+982112345678"', false)
            ->assertSee('متن سایت')
            ->assertDontSee('data-track="demo_click"', false)
            ->assertDontSee('data-video-src="', false)
            // قیمت اصلی کمتر از مبلغ پیش‌پرداخت نمایش داده نمی‌شود
            ->assertDontSee('price-old" aria-label', false)
            // بخش ارائه‌دهنده خاموش است و شرکت در پاورقی می‌آید
            ->assertDontSee('این محصول توسط')
            ->assertSee('ارائه‌شده توسط');
    }

    public function test_invalid_rich_content_is_rejected(): void
    {
        foreach ([
            'video_aparat' => 'https://evil.example/v/abc123',
            'demo_url' => 'javascript:alert(1)',
            'preorder_original_price_toman' => 'رایگان',
            'preorder_includes' => implode("\n", range(1, 9)),
            'support_channels' => [['type' => 'whatsapp', 'value' => 'https://evil.example/x', 'label' => '']],
            'features' => [['title' => '', 'description' => 'توضیح بی‌عنوان']],
        ] as $field => $value) {
            $this->post(route('landing-pages.store', $this->idea), $this->payload([$field => $value]))
                ->assertSessionHasErrors();
        }

        $this->assertSame(0, LandingPage::count());

        $this->assertSame('abc123', LandingPage::aparatHash('https://aparat.com/v/abc123'));
        $this->assertSame('abc123', LandingPage::aparatHash('https://www.aparat.com/video/video/embed/videohash/abc123/vt/frame'));
        $this->assertNull(LandingPage::aparatHash('https://www.aparat.com.evil.ir/v/abc123'));
    }

    public function test_mp4_upload_replace_and_remove(): void
    {
        $this->post(route('landing-pages.store', $this->idea), $this->payload([
            'video_aparat' => '',
            'video_file' => UploadedFile::fake()->create('intro.mp4', 500, 'video/mp4'),
        ]))->assertSessionHasNoErrors();

        $page = LandingPage::sole();
        $video = $page->contentData()['video_file'];
        Storage::disk('uploads')->assertExists($video);
        $this->get('/l/club')->assertSee('<video src="' . asset('uploads/' . $video) . '" controls preload="none"', false);

        $this->put(route('landing-pages.update', $page), $this->payload([
            'video_aparat' => '',
            'video_file' => UploadedFile::fake()->create('intro.webm', 500, 'video/webm'),
        ]))->assertSessionHasErrors('video_file');

        $this->put(route('landing-pages.update', $page), $this->payload(['video_aparat' => '', 'remove_video_file' => '1']));
        Storage::disk('uploads')->assertMissing($video);
        $this->assertNull($page->fresh()->contentData()['video_file']);
    }

    public function test_legacy_text_features_render_as_cards(): void
    {
        $this->idea->landingPages()->create(['name' => 'old', 'slug' => 'old', 'title' => 't', 'is_active' => true, 'content' => ['features' => ['ویژگی قدیمی', 'ویژگی ۲']]]);

        $this->get('/l/old')->assertOk()->assertSee('<h3>ویژگی قدیمی</h3>', false);
    }

    public function test_preview_shows_unpublished_page_without_tracking(): void
    {
        $this->post(route('landing-pages.store', $this->idea), $this->payload(['publish' => '0']))->assertSessionHasNoErrors();
        $page = LandingPage::sole();

        $this->assertFalse($page->is_active);
        $this->get('/l/club')->assertNotFound();

        $this->get(route('landing-pages.preview', $page))
            ->assertOk()
            ->assertSee('پیش‌نمایش برای مدیر')
            ->assertSee('content="noindex, nofollow"', false)
            ->assertSee('const tracking = false;', false);
        $this->assertSame(0, LandingPageEvent::count());

        $this->actingAs(User::factory()->create(['role' => AdminRole::Support]))
            ->get(route('landing-pages.preview', $page))
            ->assertForbidden();
    }

    public function test_demo_and_video_events_are_counted_outside_the_funnel(): void
    {
        $this->post(route('landing-pages.store', $this->idea), $this->payload())->assertSessionHasNoErrors();
        $page = LandingPage::sole();

        $this->get('/l/club');
        foreach (['demo_click', 'demo_click', 'video_play'] as $event) {
            $this->postJson(route('landing-pages.events.store', 'club'), ['event_name' => $event])->assertOk();
        }
        $this->postJson(route('landing-pages.events.store', 'club'), ['event_name' => 'hack'])->assertUnprocessable();

        $stats = app(FunnelStats::class)->grouped(collect([$page->id]), false)->first();
        $this->assertSame(2, $stats['demo_clicks']);
        $this->assertSame(1, $stats['video_plays']);
        $this->assertSame(1, $stats['page_views']);

        $this->get(route('ideas.show', $this->idea))->assertSee('2 دمو، 1 پخش ویدیو');
    }

    public function test_persian_digits_helper(): void
    {
        $this->assertSame('۱٬۲۰۰٬۰۰۰ تومان', Format::faDigits('1,200,000 تومان'));
        $this->assertSame('۵۰٪ تخفیف', Format::faDigits('50% تخفیف'));
        $this->assertSame('۱۴۰۵/۰۸/۰۹', Format::faDigits('1405/08/09'));
        $this->assertSame('', Format::faDigits(null));
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LandingPageManagementTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('uploads');

        $this->actingAs(User::factory()->create([
            'role' => AdminRole::Manager,
        ]));

        $this->idea = Idea::create([
            'name' => 'باشگاه‌یار',
            'slug' => 'clubyar',
            'status' => 'testing',
            'preorder_amount_rial' => 100000,
        ]);

        $this->idea->refundPolicies()->create([
            'version' => 1,
            'is_refundable' => false,
            'terms' => 'غیرقابل بازپرداخت',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'صفحه معرفی',
            'title' => 'باشگاهت را هوشمند مدیریت کن',
            'show_lead_form' => '1',
            'show_preorder_form' => '1',
        ];
    }

    private function legacyPage(): LandingPage
    {
        return $this->idea->landingPages()->create([
            'name' => 'قدیمی',
            'slug' => 'legacy',
            'title' => 'عنوان',
            'content' => [],
            'is_active' => true,
        ]);
    }

    public function test_create_with_custom_slug_and_content(): void
    {
        $this->post(route('landing-pages.store', $this->idea), $this->payload([
            'slug' => 'clubyar-intro',
            'body_title' => 'چرا باشگاه‌یار؟',
            'body_text' => "خط اول\nخط دوم",
            'features' => "مدیریت شاگردان\n\n  مدیریت پرداخت  \n",
            'faq' => [
                ['q' => 'قیمت؟', 'a' => 'ماهانه'],
                ['q' => '', 'a' => ''],
            ],
        ]))->assertRedirect(route('ideas.show', $this->idea));

        $page = LandingPage::sole();
        $content = $page->contentData();

        $this->assertSame('clubyar-intro', $page->slug);
        // فرم متنی نسخه ۱.۰ همچنان پذیرفته می‌شود: هر خط یک عنوان
        $this->assertEquals([['title' => 'مدیریت شاگردان', 'description' => null], ['title' => 'مدیریت پرداخت', 'description' => null]], $content['features']);
        // ترتیب کلیدهای شیء JSON در MySQL حفظ نمی‌شود
        // پاسخ متن ساده به HTML ویرایشگر تبدیل می‌شود (نسخه ۱.۱.۱)
        $this->assertEquals([['q' => 'قیمت؟', 'a' => '<p>ماهانه</p>']], $content['faq']);

        $this->get('/l/clubyar-intro')
            ->assertOk()
            ->assertSee('چرا باشگاه‌یار؟')
            ->assertSee('خط اول<br>', false)
            ->assertSee('مدیریت پرداخت')
            ->assertSee('قیمت؟');
    }

    public function test_content_is_escaped_on_public_page(): void
    {
        $this->post(route('landing-pages.store', $this->idea), $this->payload([
            'slug' => 'xss',
            'body_text' => '<script>alert(1)</script>',
            'features' => '<img src=x onerror=alert(1)>',
            'faq' => [['q' => '<b>q</b>', 'a' => '<script>a</script>']],
        ]));

        $this->get('/l/xss')
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('<img src=x', false)
            ->assertDontSee('<script>a</script>', false)
            // متن قالب‌دار پاک‌سازی می‌شود (اسکریپت حذف می‌شود)؛ عنوان ویژگی متن ساده و escape شده است
            ->assertDontSee('alert(1)', false)
            ->assertSee('&lt;img src=x onerror=alert(۱)&gt;', false);
    }

    public function test_invalid_and_duplicate_slugs_are_rejected(): void
    {
        $this->legacyPage();

        $this->post(route('landing-pages.store', $this->idea), $this->payload(['slug' => 'Bad Slug']))
            ->assertSessionHasErrors('slug');

        $this->post(route('landing-pages.store', $this->idea), $this->payload(['slug' => 'legacy']))
            ->assertSessionHasErrors('slug');

        $this->assertSame(1, LandingPage::count());
    }

    public function test_too_many_features_are_rejected(): void
    {
        $this->post(
            route('landing-pages.store', $this->idea),
            $this->payload(['features' => implode("\n", range(1, 13))])
        )->assertSessionHasErrors('features');
    }

    public function test_toggle_unpublishes_and_republishes(): void
    {
        $page = $this->legacyPage();

        $this->patch(route('landing-pages.toggle', $page))->assertRedirect();
        $this->assertFalse($page->fresh()->is_active);
        $this->get('/l/legacy')->assertNotFound();

        $this->patch(route('landing-pages.toggle', $page))->assertRedirect();
        $this->assertTrue($page->fresh()->is_active);
        $this->get('/l/legacy')->assertOk();
    }

    public function test_legacy_page_without_content_shows_both_forms(): void
    {
        $this->legacyPage();

        $this->get('/l/legacy')
            ->assertSee('id="lead-form"', false)
            ->assertSee('id="preorder-form"', false);
    }

    public function test_disabled_form_is_hidden_and_rejected(): void
    {
        $page = $this->legacyPage();

        $this->put(route('landing-pages.update', $page), $this->payload([
            'name' => 'قدیمی',
            'show_lead_form' => '0',
        ]))->assertRedirect();

        $this->get('/l/legacy')
            ->assertDontSee('id="lead-form"', false)
            ->assertSee('id="preorder-form"', false);

        $this->post(route('leads.store', 'legacy'), [
            'name' => 'x',
            'phone' => '09123456789',
            'consent' => '1',
        ])->assertNotFound();
    }

    public function test_update_keeps_slug_when_left_empty(): void
    {
        $page = $this->legacyPage();

        $this->put(route('landing-pages.update', $page), $this->payload([
            'slug' => '',
        ]))->assertRedirect();

        $this->assertSame('legacy', $page->fresh()->slug);
    }

    public function test_hero_image_upload_replace_and_remove(): void
    {
        $page = $this->legacyPage();

        $this->put(route('landing-pages.update', $page), $this->payload([
            'hero_image' => UploadedFile::fake()->image('a.jpg', 800, 400),
        ]))->assertSessionHasNoErrors();

        $first = $page->fresh()->contentData()['hero_image'];
        Storage::disk('uploads')->assertExists($first);
        $this->get('/l/legacy')->assertSee('uploads/' . $first, false);

        $this->put(route('landing-pages.update', $page), $this->payload([
            'hero_image' => UploadedFile::fake()->image('b.png', 800, 400),
        ]));

        $second = $page->fresh()->contentData()['hero_image'];
        Storage::disk('uploads')->assertExists($second);
        Storage::disk('uploads')->assertMissing($first);

        $this->put(route('landing-pages.update', $page), $this->payload([
            'remove_hero_image' => '1',
        ]));

        $this->assertNull($page->fresh()->contentData()['hero_image']);
        Storage::disk('uploads')->assertMissing($second);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        $page = $this->legacyPage();

        $this->put(route('landing-pages.update', $page), $this->payload([
            'hero_image' => UploadedFile::fake()->create('x.svg', 10, 'image/svg+xml'),
        ]))->assertSessionHasErrors('hero_image');

        $this->assertNull($page->fresh()->contentData()['hero_image']);
    }
}

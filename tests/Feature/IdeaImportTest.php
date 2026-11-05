<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * ورود ایده، صفحه فرود و کمپین از فایل هوش مصنوعی (نسخه ۱.۲).
 */
class IdeaImportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create(['role' => AdminRole::Manager]);
    }

    private function upload(string $content, string $name = 'ideas.md')
    {
        return $this->actingAs($this->manager)->post(route('admin.imports.upload'), [
            'file' => UploadedFile::fake()->createWithContent($name, $content),
        ]);
    }

    private function token($response): string
    {
        $response->assertRedirect();

        return basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
    }

    private function idea(array $overrides = []): string
    {
        return "```json\n" . json_encode(array_replace([
            'name' => 'ایده آزمایشی',
            'slug' => 'test-idea',
            'preorder_amount_toman' => 100000,
            'refund_policy' => ['is_refundable' => true, 'refund_window_days' => 7, 'terms' => 'تا ۷ روز'],
            'landing_page' => ['title' => 'تیتر', 'slug' => 'test-page'],
            'campaigns' => [['name' => 'دیوار', 'code' => 'test-divar', 'source' => 'divar', 'medium' => 'classified']],
        ], $overrides), JSON_UNESCAPED_UNICODE) . "\n```\n";
    }

    public function test_sample_file_imports_ideas_pages_and_campaigns(): void
    {
        $token = $this->token($this->upload(file_get_contents(resource_path('import/sample.md'))));

        $this->actingAs($this->manager)->get(route('admin.imports.preview', $token))
            ->assertOk()
            ->assertSee('باشگاه‌یار')
            ->assertSee('1 آماده')
            ->assertSee('5 کمپین')
            ->assertSee('clubyar-instagram')
            ->assertSee('490,000 تومان')
            ->assertDontSee('دارای خطا');
        $this->assertSame(0, Idea::count());

        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $token))
            ->assertRedirect(route('admin.imports.done'))
            ->assertSessionHas('success');

        $idea = Idea::sole();
        $this->assertSame('clubyar', $idea->slug);
        $this->assertSame('draft', $idea->status);
        $this->assertSame(4_900_000, $idea->preorder_amount_rial);
        $this->assertSame(200_000_000, $idea->budget_rial);
        $this->assertSame('2026-11-20', $idea->test_starts_on->toDateString());

        $policy = $idea->currentRefundPolicy;
        $this->assertSame(1, $policy->version);
        $this->assertSame(30, $policy->refund_window_days);
        $this->assertSame($this->manager->id, $policy->created_by);

        $page = LandingPage::sole();
        $this->assertFalse($page->is_active, 'صفحه تا بازبینی منتشرنشده است');
        $content = $page->contentData();
        $this->assertTrue($content['rich_text']);
        $this->assertStringContainsString('<blockquote>', $content['body_text']);
        $this->assertCount(4, $content['features']);
        $this->assertSame(980000, $content['preorder_original_price_toman']);
        $this->assertSame('<p>نسخه اول حدود ۳ ماه پس از پایان پیش‌خرید آماده می‌شود.</p>', $content['faq'][0]['a']);

        $campaigns = Campaign::orderBy('id')->get();
        $this->assertCount(5, $campaigns);
        $this->assertTrue($campaigns->every(fn ($c) => $c->landing_page_id === $page->id && $c->status === 'planned'));
        $this->assertSame(40_000_000, $campaigns[0]->budget_rial);
        // تاریخ شمسی 1405/09/01 به میلادی تبدیل شد
        $this->assertSame('2026-11-22', $campaigns[4]->starts_on->toDateString());

        $this->assertSame(1, AuditLog::where('action', 'idea.imported')->count());

        $this->actingAs($this->manager)->get(route('admin.imports.done'))->assertOk()->assertSee('باشگاهت را هوشمند مدیریت کن');
        // منتشرنشده: عمومی نیست ولی پیش‌نمایش با پیش‌خرید کار می‌کند
        $this->get('/l/clubyar')->assertNotFound();
        $this->actingAs($this->manager)->get(route('landing-pages.preview', $page))->assertOk()->assertSee('id="preorder-form"', false);

        // فایل موقت پس از ورود حذف می‌شود
        $this->actingAs($this->manager)->get(route('admin.imports.preview', $token))->assertNotFound();
    }

    public function test_second_import_gets_new_slugs_with_warnings(): void
    {
        $sample = file_get_contents(resource_path('import/sample.md'));

        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $this->token($this->upload($sample))));
        $token = $this->token($this->upload($sample));

        $this->actingAs($this->manager)->get(route('admin.imports.preview', $token))
            ->assertSee('نامک ایده «clubyar» تکراری بود')
            ->assertSee('کد کمپین 1 «clubyar-divar» تکراری بود');

        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $token))->assertSessionHasNoErrors();

        $this->assertSame(2, Idea::count());
        $this->assertSame(2, LandingPage::count());
        $this->assertSame(10, Campaign::count());
        $this->assertSame(10, Campaign::distinct()->count('slug'));
    }

    public function test_invalid_ideas_block_import_unless_skipped(): void
    {
        $file = $this->idea() . $this->idea([
            'name' => 'ایده خراب',
            'slug' => 'broken',
            'preorder_amount_toman' => '۵۰۰',
            'landing_page' => ['slug' => 'broken-page'],
            'campaigns' => [['name' => 'x', 'code' => 'Bad Code', 'source' => 'دیوار']],
        ]) . "```json\n{\"name\": \"قطع‌شده\", \"campaigns\": [\n```\n";

        $token = $this->token($this->upload($file));

        $this->actingAs($this->manager)->get(route('admin.imports.preview', $token))
            ->assertSee('1 آماده')
            ->assertSee('1 دارای خطا')
            ->assertSee('مبلغ پیش‌پرداخت حداقل ۱٬۰۰۰ تومان است.')
            ->assertSee('کمپین 1 ← کد')
            ->assertSee('صفحه فرود ← عنوان')
            ->assertSee('بلوک JSON شماره 3 خوانا نیست');

        // بدون تأیید رد کردن، هیچ‌چیز ساخته نمی‌شود
        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $token))->assertSessionHasErrors('import');
        $this->assertSame(0, Idea::count());

        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $token), ['skip_invalid' => '1'])->assertSessionHasNoErrors();
        $this->assertSame(['test-idea'], Idea::pluck('slug')->all());
        $this->assertSame(['test-divar'], Campaign::pluck('slug')->all());
    }

    public function test_formats_and_html_are_handled_safely(): void
    {
        // JSON خالص با ویرگول اضافه و چند صفحه
        $json = '{"ideas": [{"name": "دو صفحه", "slug": "two-pages",
            "landing_pages": [{"title": "صفحه اول", "slug": "first", "body": "<p onclick=\"x()\">سلام<script>alert(1)</script></p>", "publish": true},
                              {"title": "صفحه دوم", "slug": "second", "features": ["ویژگی ساده"],}],
            "campaigns": [{"name": "الف", "landing_page": "second"}, {"name": "ب", "source": "instagram"},],
        }]}';

        $token = $this->token($this->upload($json, 'ideas.json'));
        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $token))->assertSessionHasNoErrors();

        $first = LandingPage::where('slug', 'first')->sole();
        $second = LandingPage::where('slug', 'second')->sole();
        $this->assertTrue($first->is_active);
        $this->assertSame('<p>سلام</p>', $first->contentData()['body_text']);
        $this->assertSame([['title' => 'ویژگی ساده', 'description' => null]], $second->contentData()['features']);
        $this->assertSame([$second->id, $first->id], Campaign::orderBy('id')->pluck('landing_page_id')->all());
        $this->assertSame(['two-pages-1', 'two-pages-instagram'], Campaign::orderBy('id')->pluck('slug')->all());

        // متن چسبانده‌شده هم پذیرفته می‌شود؛ ارجاع به صفحه ناموجود خطاست
        $this->actingAs($this->manager)->post(route('admin.imports.upload'), ['text' => $this->idea(['slug' => 'pasted', 'campaigns' => [['name' => 'x', 'landing_page' => 'nope']]])])
            ->assertRedirect();

        $this->actingAs($this->manager)->post(route('admin.imports.upload'), ['text' => 'فقط متن بدون JSON'])
            ->assertSessionHasErrors('file');
        $this->upload('x', 'ideas.pdf')->assertSessionHasErrors('file');
    }

    public function test_permissions_and_downloads(): void
    {
        $token = $this->token($this->upload($this->idea()));

        // فقط بارگذارکننده پیش‌نمایش را می‌بیند
        $this->actingAs(User::factory()->create(['role' => AdminRole::Manager]))->get(route('admin.imports.preview', $token))->assertNotFound();
        $this->actingAs(User::factory()->create(['role' => AdminRole::Support]))->get(route('admin.imports.index'))->assertForbidden();

        $this->actingAs($this->manager)->get(route('admin.imports.index'))->assertOk()->assertSee('دریافت راهنمای پرامپت');
        $guide = $this->actingAs($this->manager)->get(route('admin.imports.guide'));
        $guide->assertOk()->assertDownload('idealab-ai-prompt.md');
        $this->assertStringContainsString('شروع پرامپت', file_get_contents($guide->baseResponse->getFile()->getPathname()));
        $this->actingAs($this->manager)->get(route('admin.imports.sample'))->assertOk()->assertDownload('idealab-import-sample.md');
    }
}

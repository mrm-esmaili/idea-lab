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
            ->assertSee('1 جدید')
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

    public function test_duplicate_idea_is_updated_by_default_or_created_new_on_request(): void
    {
        $sample = file_get_contents(resource_path('import/sample.md'));
        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $this->token($this->upload($sample))));

        // بار دوم: پیش‌فرض به‌روزرسانی همان ایده، صفحه و کمپین‌ها
        $token = $this->token($this->upload($sample));
        $idea = Idea::sole();
        $this->actingAs($this->manager)->get(route('admin.imports.preview', $token))
            ->assertSee('این ایده تکراری است؛ چه کنیم؟')
            ->assertSee('به‌روزرسانی «باشگاه‌یار»')
            ->assertSee('1 به‌روزرسانی')
            ->assertSee('name="decision[0]" value="' . $idea->id . '"', false)
            ->assertDontSee('تکراری بود');

        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $token))
            ->assertSessionHas('success', fn ($m) => str_contains($m, '1 ایده موجود به‌روزرسانی شد'));
        $this->assertSame(1, Idea::count());
        $this->assertSame(1, LandingPage::count());
        $this->assertSame(5, Campaign::count());
        // شرایط یکسان نسخه جدید نمی‌سازد
        $this->assertSame(1, $idea->refundPolicies()->count());
        $this->assertSame(1, AuditLog::where('action', 'idea.import_updated')->count());

        // انتخاب «ساخت ایده جدید»: نامک‌ها و کدهای جدید با هشدار
        $token = $this->token($this->upload($sample));
        $this->actingAs($this->manager)->get(route('admin.imports.preview', [$token, 'decision' => [0 => 'new']]))
            ->assertSee('1 جدید')
            ->assertSee('نامک ایده «clubyar» تکراری بود')
            ->assertSee('کد کمپین 1 «clubyar-divar» تکراری بود');
        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $token), ['decision' => [0 => 'new']])->assertSessionHasNoErrors();

        $this->assertSame(2, Idea::count());
        $this->assertSame(10, Campaign::count());
        $this->assertSame(10, Campaign::distinct()->count('slug'));
    }

    /**
     * حالت مالک: ایده‌ها فقط با نام و توضیح دستی ساخته شده‌اند؛ فایل همان ایده‌ها
     * را با صفحه و کمپین کامل می‌کند و تکراری نمی‌سازد.
     */
    public function test_manually_created_ideas_are_completed_not_duplicated(): void
    {
        $existing = Idea::create(['name' => 'باشگاه يار', 'slug' => 'my-own-slug', 'description' => 'توضیح دستی', 'status' => 'testing']);
        $sample = str_replace('"slug": "clubyar",' . "\n  \"description\"", '"description"', file_get_contents(resource_path('import/sample.md')));
        $sample = preg_replace('/"name": "باشگاه‌یار",\n  "slug": "clubyar",/u', '"name": "باشگاه‌یار",', $sample);

        $token = $this->token($this->upload($sample));
        $this->actingAs($this->manager)->get(route('admin.imports.preview', $token))
            ->assertSee('به‌روزرسانی «باشگاه يار»')
            ->assertSee('هم‌نام')
            ->assertSee('نام، توضیح، بودجه، مبلغ پیش‌پرداخت');

        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $token))->assertSessionHasNoErrors();

        $idea = Idea::sole();
        $this->assertSame($existing->id, $idea->id);
        $this->assertSame('my-own-slug', $idea->slug, 'نامک ایده موجود تغییر نمی‌کند');
        $this->assertSame('testing', $idea->status, 'وضعیت ایده موجود تغییر نمی‌کند');
        $this->assertSame('باشگاه‌یار', $idea->name);
        $this->assertStringStartsWith('نرم‌افزار ابری', $idea->description);
        $this->assertSame(4_900_000, $idea->preorder_amount_rial);
        $this->assertSame(1, $idea->refundPolicies()->count());
        $this->assertSame(1, $idea->landingPages()->count());
        $this->assertFalse($idea->landingPages()->first()->is_active);
        $this->assertSame(5, Campaign::count());
    }

    public function test_update_keeps_media_publish_state_and_address(): void
    {
        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $this->token($this->upload(file_get_contents(resource_path('import/sample.md'))))));

        $page = LandingPage::sole();
        $content = $page->content;
        $content['hero_image'] = 'landing-pages/hero.jpg';
        $content['video_aparat'] = 'Abc123';
        $page->update(['is_active' => true, 'content' => $content]);
        Campaign::where('slug', 'clubyar-divar')->update(['status' => 'running']);

        // فایل جدید: تیتر، متن معرفی، شرایط بازپرداخت و یک کمپین تغییر کرده، یک کمپین تازه
        $file = $this->idea([
            'name' => 'باشگاه‌یار', 'slug' => 'clubyar',
            'refund_policy' => ['is_refundable' => true, 'refund_window_days' => 14, 'terms' => 'تا ۱۴ روز'],
            'landing_page' => ['title' => 'تیتر تازه', 'body' => '<h2>متن تازه</h2>'],
            'campaigns' => [
                ['name' => 'دیوار با بودجه بیشتر', 'code' => 'clubyar-divar', 'budget_toman' => 9000000],
                ['name' => 'ایتا', 'code' => 'clubyar-eitaa', 'source' => 'eitaa'],
            ],
        ]);
        $token = $this->token($this->upload($file));
        $this->actingAs($this->manager)->post(route('admin.imports.confirm', $token))->assertSessionHasNoErrors();

        $page->refresh();
        $content = $page->contentData();
        $this->assertTrue($page->is_active);
        $this->assertSame('clubyar', $page->slug);
        $this->assertSame('تیتر تازه', $page->title);
        $this->assertSame('<h2>متن تازه</h2>', $content['body_text']);
        $this->assertSame('landing-pages/hero.jpg', $content['hero_image']);
        $this->assertSame('Abc123', $content['video_aparat']);
        // کلیدهایی که در فایل نبودند حفظ می‌شوند
        $this->assertCount(4, $content['features']);
        $this->assertSame('۵۰٪ تخفیف پیش‌خرید', $content['preorder_badge']);

        $divar = Campaign::where('slug', 'clubyar-divar')->sole();
        $this->assertSame('دیوار با بودجه بیشتر', $divar->name);
        $this->assertSame(90_000_000, $divar->budget_rial);
        $this->assertSame('running', $divar->status, 'وضعیت کمپین بدون کلید status تغییر نمی‌کند');
        $this->assertSame('divar', $divar->source);
        $this->assertSame(6, Campaign::count());

        // شرایط متفاوت ← نسخه ۲
        $this->assertSame([2, 1], Idea::sole()->refundPolicies()->pluck('version')->all());
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
            ->assertSee('1 جدید')
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

        Idea::create(['name' => 'ایده دستی', 'slug' => 'manual-idea', 'status' => 'draft']);
        $existing = $this->actingAs($this->manager)->get(route('admin.imports.existing'));
        $existing->assertOk()->assertDownload('idealab-existing-ideas.md');
        $this->assertStringContainsString('name: "ایده دستی" — slug: "manual-idea" — (بدون صفحه فرود)', $existing->getContent());
    }
}

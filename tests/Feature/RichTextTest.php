<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\User;
use App\Support\RichText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ویرایشگر متن بخش‌های توضیحی صفحه فرود (نسخه ۱.۱.۱).
 */
class RichTextTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowed_tags_are_kept_and_attributes_stripped(): void
    {
        $html = '<h1 class="x">۱</h1><h2>2</h2><h3>3</h3><h4>4</h4><h5>5</h5><h6 style="color:red">6</h6>'
            . '<p onclick="steal()">متن <strong>پررنگ</strong> <b>b</b> <em>کج</em> <i>i</i> <u>زیر</u> <s>خط</s><br>خط بعد</p>'
            . '<ul><li>یک</li></ul><ol><li>دو</li></ol><blockquote>نقل</blockquote><hr>';

        $this->assertSame(
            '<h1>۱</h1><h2>2</h2><h3>3</h3><h4>4</h4><h5>5</h5><h6>6</h6>'
            . '<p>متن <strong>پررنگ</strong> <b>b</b> <em>کج</em> <i>i</i> <u>زیر</u> <s>خط</s><br>خط بعد</p>'
            . '<ul><li>یک</li></ul><ol><li>دو</li></ol><blockquote>نقل</blockquote><hr>',
            RichText::sanitize($html)
        );
    }

    public function test_dangerous_content_is_removed(): void
    {
        $dirty = '<p>سلام<script>alert(1)</script><style>p{}</style></p>'
            . '<iframe src="https://evil.example"></iframe><img src=x onerror=alert(2)><svg onload=alert(3)></svg>'
            . '<!-- comment --><form><input name=x></form>'
            . '<a href="javascript:alert(4)">بد</a><a href="data:text/html,x">داده</a><a href=" JaVaScRiPt:alert(5)">۲</a>'
            . '<a href="https://itmb.ir" onclick="x()" target="_self">خوب</a><a href="tel:+989121234567">تلفن</a>'
            . '<div><span style="font-size:40px">داخل جعبه</span></div><font color=red>فونت</font>';

        $clean = RichText::sanitize($dirty);

        foreach (['script', 'style', 'iframe', 'img', 'svg', 'form', 'input', 'onclick', 'onerror', 'javascript', 'data:', 'comment', 'span', 'font', 'div', '_self'] as $needle) {
            $this->assertStringNotContainsStringIgnoringCase($needle, $clean, $needle);
        }

        $this->assertStringContainsString('<a href="https://itmb.ir" target="_blank" rel="noopener nofollow">خوب</a>', $clean);
        $this->assertStringContainsString('<a href="tel:+989121234567">تلفن</a>', $clean);
        // متن تگ‌های ناشناخته و پیوندهای ناامن می‌ماند
        $this->assertStringContainsString('بد', $clean);
        $this->assertStringContainsString('داخل جعبه', $clean);
        $this->assertStringContainsString('فونت', $clean);
    }

    public function test_empty_and_plain_text_conversion(): void
    {
        $this->assertNull(RichText::sanitize('<p><br></p>'));
        $this->assertNull(RichText::sanitize('  '));
        $this->assertSame('<hr>', RichText::sanitize('<hr>'));
        $this->assertSame('<p>خط ۱<br>' . "\n" . 'خط &lt;۲&gt;</p><p>بند دوم</p>', RichText::fromPlain("خط ۱\nخط <۲>\n\nبند دوم"));

        // اعداد فارسی فقط در متن، نه در نشانی پیوند
        $this->assertSame(
            '<p>تماس ۰۹۱۲ <a href="tel:0912">۱۲</a></p>',
            RichText::render('<p>تماس 0912 <a href="tel:0912">12</a></p>')
        );
    }

    public function test_editor_fields_are_saved_sanitized_and_rendered(): void
    {
        $manager = User::factory()->create(['role' => AdminRole::Manager]);
        $idea = Idea::create(['name' => 'ایده', 'slug' => 'idea', 'status' => 'testing', 'preorder_amount_rial' => 1_000_000]);
        $idea->refundPolicies()->create(['version' => 1, 'is_refundable' => true, 'refund_window_days' => 7, 'terms' => 't']);

        $this->actingAs($manager)->get(route('landing-pages.create', $idea))
            ->assertOk()
            ->assertSee('data-rich-editor', false)
            ->assertSee('js/rich-editor.js', false);

        $this->actingAs($manager)->post(route('landing-pages.store', $idea), [
            'name' => 'p', 'slug' => 'rich', 'title' => 't', 'show_lead_form' => '1', 'show_preorder_form' => '1',
            'body_text' => '<h2>چرا ما؟</h2><ul><li>سریع</li></ul><blockquote>نقل قول</blockquote><hr><p>دارای 3 مزیت<script>x()</script></p>',
            'faq' => [['q' => 'قیمت؟', 'a' => '<p><strong>ماهانه</strong></p><ol><li>پایه</li></ol>']],
            'preorder_intro' => '<p>معرفی <em>کوتاه</em></p>',
            'preorder_guarantee' => '<h4>ضمانت</h4><p>بازگشت وجه</p>',
        ])->assertSessionHasNoErrors();

        $content = LandingPage::sole()->contentData();
        $this->assertTrue($content['rich_text']);
        $this->assertSame('<h2>چرا ما؟</h2><ul><li>سریع</li></ul><blockquote>نقل قول</blockquote><hr><p>دارای 3 مزیت</p>', $content['body_text']);

        $this->get('/l/rich')
            ->assertOk()
            ->assertSee('<div class="body-text rich-text"><h2>چرا ما؟</h2><ul><li>سریع</li></ul><blockquote>نقل قول</blockquote><hr><p>دارای ۳ مزیت</p></div>', false)
            ->assertSee('<div class="rich-text"><p><strong>ماهانه</strong></p><ol><li>پایه</li></ol></div>', false)
            ->assertSee('<div class="form-intro rich-text"><p>معرفی <em>کوتاه</em></p></div>', false)
            ->assertSee('<div class="guarantee rich-text"><h4>ضمانت</h4><p>بازگشت وجه</p></div>', false)
            ->assertDontSee('x()', false);

        // ویرایش دوباره همان HTML را در ویرایشگر نشان می‌دهد
        $this->actingAs($manager)->get(route('landing-pages.edit', LandingPage::sole()))
            ->assertSee('&lt;h2&gt;چرا ما؟&lt;/h2&gt;', false);

        // طول متن خالص محدود است، نه طول HTML
        $this->actingAs($manager)->put(route('landing-pages.update', LandingPage::sole()), [
            'name' => 'p', 'title' => 't', 'preorder_guarantee' => '<p>' . str_repeat('ض', 1001) . '</p>',
        ])->assertSessionHasErrors('preorder_guarantee');
    }

    public function test_legacy_plain_text_pages_render_as_paragraphs(): void
    {
        $idea = Idea::create(['name' => 'ایده', 'slug' => 'idea', 'status' => 'testing']);
        $idea->landingPages()->create([
            'name' => 'old', 'slug' => 'old', 'title' => 't', 'is_active' => true,
            'content' => ['body_text' => "خط اول\nخط <b>دوم</b>\n\nبند دوم", 'faq' => [['q' => 'q', 'a' => "پاسخ\nادامه"]]],
        ]);

        $this->get('/l/old')
            ->assertOk()
            ->assertSee('<p>خط اول<br>', false)
            ->assertSee('خط &lt;b&gt;دوم&lt;/b&gt;</p><p>بند دوم</p>', false)
            ->assertSee('<p>پاسخ<br>', false);
    }
}

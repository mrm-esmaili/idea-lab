<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Contact;
use App\Models\ContactGroup;
use App\Models\DataExport;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\Preorder;
use App\Models\SmsMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CrmTest extends TestCase
{
    use RefreshDatabase;

    private Idea $ideaA;

    private Idea $ideaB;

    private LandingPage $pageA;

    private LandingPage $pageB;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('fa');

        $this->ideaA = Idea::create(['name' => 'باشگاه‌یار', 'slug' => 'a', 'status' => 'testing']);
        $this->ideaB = Idea::create(['name' => 'کافه‌یار', 'slug' => 'b', 'status' => 'testing']);
        $this->pageA = $this->ideaA->landingPages()->create(['name' => 'A', 'slug' => 'page-a', 'title' => 't', 'is_active' => true]);
        $this->pageB = $this->ideaB->landingPages()->create(['name' => 'B', 'slug' => 'page-b', 'title' => 't', 'is_active' => true]);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => AdminRole::Manager]);
    }

    private function submitLead(string $slug, string $mobile, array $query = []): void
    {
        $visit = $this->get("/l/{$slug}?" . http_build_query($query));
        $cookie = $visit->getCookie('visitor_uuid', false);

        $this->withUnencryptedCookie('visitor_uuid', $cookie->getValue())
            ->post(route('leads.store', $slug), ['name' => 'مخاطب', 'phone' => $mobile, 'consent' => '1']);
    }

    private function contactIds(string $url): array
    {
        return $this->actingAs($this->manager())->get($url)->viewData('contacts')->pluck('mobile')->sort()->values()->all();
    }

    /**
     * سه مخاطب نمونه با ویژگی‌های متفاوت.
     */
    private function fixtures(): array
    {
        $a = Contact::create(['mobile' => '09120000001', 'name' => 'الف']);
        $a->forceFill(['first_source' => 'divar', 'first_campaign' => 'divar-1'])->save();
        $a->setMarketingConsent(true, 'test');
        $a->interests()->attach($this->ideaA->id, ['source' => 'lead_form', 'created_at' => now()]);
        Preorder::create(['landing_page_id' => $this->pageA->id, 'contact_id' => $a->id, 'name' => 'الف', 'phone' => $a->mobile, 'payment_status' => 'paid']);

        $b = Contact::create(['mobile' => '09120000002', 'name' => 'ب']);
        $b->interests()->attach($this->ideaB->id, ['source' => 'preorder', 'campaign' => 'insta-1', 'created_at' => now()]);
        $bPreorder = Preorder::create(['landing_page_id' => $this->pageB->id, 'contact_id' => $b->id, 'name' => 'ب', 'phone' => $b->mobile, 'payment_status' => 'unpaid']);

        $c = Contact::create(['mobile' => '09120000003', 'name' => 'ج']);
        $c->forceFill(['created_at' => now()->subDays(40)])->save();
        $this->pageA->leads()->create(['contact_id' => $c->id, 'name' => 'ج', 'phone' => $c->mobile, 'status' => 'contacted']);

        return [$a, $b, $c, $bPreorder];
    }

    public function test_form_submission_records_interest_and_first_touch(): void
    {
        $this->get('/l/page-b?utm_campaign=visit-only');
        $this->assertSame(0, DB::table('contact_idea_interests')->count(), 'بازدید به‌تنهایی علاقه نیست');

        $this->submitLead('page-a', '09121111111', ['utm_source' => 'divar', 'utm_medium' => 'paid', 'utm_campaign' => 'divar-1']);
        $this->submitLead('page-a', '09121111111', ['utm_campaign' => 'other']);
        $this->submitLead('page-b', '۰۹۱۲۱۱۱۱۱۱۱', ['utm_source' => 'instagram', 'utm_campaign' => 'insta-1']);

        $contact = Contact::sole();
        $this->assertSame(2, $this->pageA->leads()->where('contact_id', $contact->id)->count());

        $interests = DB::table('contact_idea_interests')->orderBy('idea_id')->get();
        $this->assertCount(2, $interests);
        $this->assertSame('divar-1', $interests[0]->campaign, 'اولین کمپین علاقه حفظ می‌شود');
        $this->assertSame('insta-1', $interests[1]->campaign);

        $this->assertSame($this->pageA->id, $contact->first_landing_page_id);
        $this->assertSame('divar', $contact->first_source);
        $this->assertSame('paid', $contact->first_medium);
        $this->assertSame('divar-1', $contact->first_campaign);
    }

    public function test_segment_filters(): void
    {
        $this->fixtures();

        $this->assertSame(['09120000001'], $this->contactIds('/admin/contacts?ideas[]=' . $this->ideaA->id));
        $this->assertSame(['09120000001'], $this->contactIds('/admin/contacts?payment=paid'));
        $this->assertSame(['09120000002'], $this->contactIds('/admin/contacts?payment=unpaid'));
        $this->assertSame(['09120000003'], $this->contactIds('/admin/contacts?payment=none'));
        $this->assertSame(['09120000001'], $this->contactIds('/admin/contacts?consent=yes'));
        $this->assertSame(['09120000002', '09120000003'], $this->contactIds('/admin/contacts?consent=no'));
        $this->assertSame(['09120000001'], $this->contactIds('/admin/contacts?campaign=divar-1'));
        $this->assertSame(['09120000002'], $this->contactIds('/admin/contacts?campaign=insta-1'));
        $this->assertSame(['09120000001'], $this->contactIds('/admin/contacts?source=divar'));
        $this->assertSame(['09120000003'], $this->contactIds('/admin/contacts?lead_status=contacted'));
        $this->assertSame(['09120000001', '09120000002'], $this->contactIds('/admin/contacts?joined_within_days=30'));
        $this->assertSame(['09120000002'], $this->contactIds('/admin/contacts?q=0000002'));
        $this->assertSame([], $this->contactIds('/admin/contacts?payment=paid&consent=no'));
    }

    public function test_dynamic_group_membership_updates_automatically(): void
    {
        [, , , $bPreorder] = $this->fixtures();

        $this->actingAs($this->manager())
            ->post(route('admin.groups.store'), [
                'name' => 'خریداران',
                'type' => 'dynamic',
                'rules' => ['payment' => 'paid'],
            ])->assertRedirect();

        $group = ContactGroup::sole();
        $this->assertSame(1, $group->membersQuery()->count());

        $bPreorder->update(['payment_status' => 'paid']);

        $this->assertSame(2, $group->membersQuery()->count());
        $this->get(route('admin.groups.show', $group))->assertOk()->assertSee('09120000002');
    }

    public function test_dynamic_group_requires_rules_and_type_is_fixed(): void
    {
        $this->actingAs($this->manager())
            ->post(route('admin.groups.store'), ['name' => 'همه', 'type' => 'dynamic', 'rules' => []])
            ->assertSessionHasErrors('rules');

        $group = ContactGroup::create(['name' => 'دستی', 'type' => 'manual']);

        $this->put(route('admin.groups.update', $group), ['name' => 'x', 'type' => 'dynamic', 'rules' => ['consent' => 'yes']])
            ->assertSessionHasErrors('type');
    }

    public function test_manual_group_membership_from_contact_profile(): void
    {
        [$a] = $this->fixtures();
        $manual = ContactGroup::create(['name' => 'VIP', 'type' => 'manual']);
        $dynamic = ContactGroup::create(['name' => 'رضایت', 'type' => 'dynamic', 'rules' => ['consent' => 'yes']]);

        $this->actingAs($this->manager());

        $this->post(route('admin.contacts.groups.store', $a), ['contact_group_id' => $manual->id])->assertRedirect();
        $this->post(route('admin.contacts.groups.store', $a), ['contact_group_id' => $manual->id]);
        $this->assertSame(1, $manual->membersQuery()->count());

        $this->post(route('admin.contacts.groups.store', $a), ['contact_group_id' => $dynamic->id])
            ->assertSessionHasErrors('contact_group_id');

        $this->get(route('admin.contacts.show', $a))->assertOk()->assertSee('VIP')->assertSee('رضایت');

        $this->delete(route('admin.contacts.groups.destroy', [$a, $manual]))->assertRedirect();
        $this->assertSame(0, $manual->membersQuery()->count());
    }

    public function test_profile_shows_interests_and_timeline(): void
    {
        [$a] = $this->fixtures();
        SmsMessage::create(['mobile' => $a->mobile, 'template_key' => 'otp', 'category' => 'otp', 'provider' => 'log', 'status' => 'sent']);
        $preorder = $a->preorders()->first();
        $preorder->payments()->create(['amount_rial' => 500_000, 'status' => 'paid', 'ref_id' => 'R-55']);

        $this->actingAs($this->manager())
            ->post(route('admin.contacts.interests.store', $a), ['idea_id' => $this->ideaB->id]);

        $this->get(route('admin.contacts.show', $a))
            ->assertOk()
            ->assertSee('باشگاه‌یار')
            ->assertSee('کافه‌یار')
            ->assertSee('ثبت مدیر')
            ->assertSee('درخواست پیش‌خرید')
            ->assertSee('R-55')
            ->assertSee('پیامک کد ورود')
            ->assertSee('رضایت دریافت پیامک تبلیغاتی');

        $this->delete(route('admin.contacts.interests.destroy', [$a, $this->ideaB]))->assertRedirect();
        $this->assertFalse($a->interests()->where('ideas.id', $this->ideaB->id)->exists());
    }

    public function test_export_is_minimal_safe_and_logged(): void
    {
        [$a] = $this->fixtures();
        $a->update(['name' => '=HYPERLINK("http://evil")', 'email' => 'secret@example.com']);

        $manager = $this->manager();
        $response = $this->actingAs($manager)->get(route('admin.contacts.export', ['consented_only' => 1]));
        $response->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('09120000001', $csv);
        $this->assertStringNotContainsString('09120000002', $csv, 'فقط دارای رضایت');
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringNotContainsString('secret@example.com', $csv);

        $log = DataExport::sole();
        $this->assertSame($manager->id, $log->user_id);
        $this->assertSame(1, $log->row_count);
        $this->assertTrue($log->filters['consented_only']);
    }

    public function test_group_export_and_permissions(): void
    {
        $this->fixtures();
        $group = ContactGroup::create(['name' => 'g', 'type' => 'dynamic', 'rules' => ['payment' => 'unpaid']]);

        $csv = $this->actingAs($this->manager())->get(route('admin.groups.export', $group))->streamedContent();
        $this->assertStringContainsString('09120000002', $csv);
        $this->assertStringNotContainsString('09120000001', $csv);
        $this->assertSame('group:' . $group->id, DataExport::sole()->scope);

        $support = User::factory()->create(['role' => AdminRole::Support]);
        $this->actingAs($support)->get(route('admin.contacts.index'))->assertOk();
        $this->actingAs($support)->get(route('admin.contacts.export'))->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => AdminRole::Finance]))
            ->get(route('admin.contacts.index'))
            ->assertForbidden();
    }

    public function test_account_dashboard_lists_declared_interests(): void
    {
        [$a] = $this->fixtures();
        $a->interests()->attach($this->ideaB->id, ['source' => 'admin', 'created_at' => now()]);

        $this->actingAs($a, 'contact')
            ->withSession(['contact_login_at' => now()->getTimestamp()])
            ->get(route('account.dashboard'))
            ->assertOk()
            ->assertSee('باشگاه‌یار')
            ->assertSee('کافه‌یار');
    }

    public function test_leads_search_and_link_to_contact(): void
    {
        [, , $c] = $this->fixtures();

        $this->actingAs($this->manager())
            ->get('/admin/leads?q=0000003')
            ->assertOk()
            ->assertSee(route('admin.contacts.show', $c));
    }
}

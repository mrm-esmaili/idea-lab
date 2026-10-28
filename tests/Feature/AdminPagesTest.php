<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\Preorder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPagesTest extends TestCase
{
    use RefreshDatabase;

    private Idea $idea;

    protected function setUp(): void
    {
        parent::setUp();

        $this->idea = Idea::create([
            'name' => 'باشگاه‌یار',
            'slug' => 'clubyar',
            'status' => 'testing',
        ]);

        $page = $this->idea->landingPages()->create([
            'name' => 'صفحه معرفی',
            'slug' => 'clubyar',
            'title' => 'عنوان',
            'is_active' => true,
        ]);

        $page->leads()->create([
            'name' => 'سرنخ نمونه',
            'phone' => '09123456789',
            'status' => 'interested',
            'campaign' => 'clubyar01',
        ]);

        $preorder = Preorder::create([
            'landing_page_id' => $page->id,
            'name' => 'خریدار نمونه',
            'phone' => '09123456789',
            'status' => 'interested',
            'payment_status' => 'paid',
            'amount_rial' => 1_200_000,
        ]);

        $preorder->payments()->create([
            'amount_rial' => 1_200_000,
            'status' => 'paid',
            'authority' => 'A0001',
            'ref_id' => '987654',
            'verified_at' => now(),
        ]);
    }

    private function admin(AdminRole $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_all_admin_pages_render_with_data(): void
    {
        $this->actingAs($this->admin(AdminRole::SuperAdmin));

        $this->get('/admin')->assertOk()->assertSee('صفحه معرفی');
        $this->get('/ideas')->assertOk()->assertSee('باشگاه‌یار')->assertSee('در حال آزمایش');
        $this->get('/ideas/create')->assertOk();
        $this->get(route('ideas.show', $this->idea))->assertOk()->assertSee('/l/clubyar');
        $this->get(route('landing-pages.create', $this->idea))->assertOk();
        $this->get('/admin/leads')->assertOk()->assertSee('سرنخ نمونه')->assertSee('علاقه‌مند');
        $this->get('/admin/preorders')->assertOk()->assertSee('خریدار نمونه')->assertSee('پرداخت‌شده');
        $this->get(route('admin.preorders.show', Preorder::sole()))->assertOk()->assertSee('987654')->assertSee('120,000 تومان');
        $this->get('/admin/payments')->assertOk()->assertSee('987654');
        $this->get('/admin/users')->assertOk();
    }

    public function test_menu_only_shows_permitted_sections(): void
    {
        $this->actingAs($this->admin(AdminRole::Finance))
            ->get('/admin')
            ->assertSee(route('admin.preorders.index'))
            ->assertDontSee(route('admin.leads.index'))
            ->assertDontSee(route('ideas.index'));

        $this->actingAs($this->admin(AdminRole::Manager))
            ->get('/admin')
            ->assertSee(route('admin.leads.index'))
            ->assertSee(route('ideas.index'));
    }

    public function test_validation_errors_are_shown_in_layout(): void
    {
        $this->actingAs($this->admin(AdminRole::Manager));

        $this->from('/ideas/create')
            ->followingRedirects()
            ->post('/ideas', ['name' => ''])
            ->assertOk()
            ->assertSee('is-invalid');
    }
}

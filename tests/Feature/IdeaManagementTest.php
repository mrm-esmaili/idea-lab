<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IdeaManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = User::factory()->create([
            'role' => AdminRole::Manager,
        ]);

        $this->actingAs($this->manager);
    }

    private function idea(array $attributes = []): Idea
    {
        return Idea::create($attributes + [
            'name' => 'باشگاه‌یار',
            'slug' => 'clubyar',
            'status' => 'draft',
        ]);
    }

    private function withPage(Idea $idea)
    {
        return $idea->landingPages()->create([
            'name' => 'صفحه',
            'slug' => 'clubyar-page',
            'title' => 'عنوان',
            'is_active' => true,
        ]);
    }

    public function test_persian_digit_name_gets_fallback_slug(): void
    {
        $this->post('/ideas', ['name' => '۱۲۳'])->assertRedirect();
        $this->post('/ideas', ['name' => '۱۲۳'])->assertRedirect();

        $slugs = Idea::pluck('slug');

        $this->assertSame('idea', $slugs[0]);
        $this->assertStringStartsWith('idea-', $slugs[1]);
    }

    public function test_update_keeps_slug(): void
    {
        $idea = $this->idea();

        $this->put(route('ideas.update', $idea), [
            'name' => 'نام جدید',
            'description' => 'توضیح',
        ])->assertRedirect(route('ideas.show', $idea));

        $idea->refresh();
        $this->assertSame('نام جدید', $idea->name);
        $this->assertSame('clubyar', $idea->slug);
    }

    public function test_allowed_status_change_is_recorded(): void
    {
        $idea = $this->idea();

        $this->patch(route('ideas.status', $idea), [
            'status' => 'testing',
            'note' => 'شروع کمپین',
        ])->assertRedirect();

        $this->assertSame('testing', $idea->fresh()->status);

        $change = $idea->statusChanges()->sole();
        $this->assertSame('draft', $change->from_status);
        $this->assertSame('testing', $change->to_status);
        $this->assertSame('شروع کمپین', $change->note);
        $this->assertSame($this->manager->id, $change->user_id);

        $this->get(route('ideas.show', $idea))->assertSee('شروع کمپین');
    }

    public function test_disallowed_status_change_is_rejected(): void
    {
        $idea = $this->idea();

        $this->patch(route('ideas.status', $idea), [
            'status' => 'launched',
        ])->assertSessionHasErrors('status');

        $this->assertSame('draft', $idea->fresh()->status);
        $this->assertSame(0, $idea->statusChanges()->count());
    }

    public function test_archiving_hides_public_pages_and_blocks_forms(): void
    {
        $idea = $this->idea();
        $page = $this->withPage($idea);

        $this->post(route('ideas.archive', $idea))->assertRedirect();

        $this->get('/l/' . $page->slug)->assertNotFound();
        $this->post(route('leads.store', $page->slug), [
            'name' => 'x',
            'phone' => '09123456789',
            'consent' => '1',
        ])->assertNotFound();

        $this->get('/ideas')->assertDontSee('باشگاه‌یار');
        $this->get('/ideas?archived=1')->assertSee('باشگاه‌یار');

        $this->post(route('ideas.restore', $idea))->assertRedirect();
        $this->get('/l/' . $page->slug)->assertOk();
    }

    public function test_idea_with_landing_pages_cannot_be_deleted(): void
    {
        $idea = $this->idea();
        $this->withPage($idea);

        $this->delete(route('ideas.destroy', $idea))
            ->assertSessionHasErrors('idea');

        $this->assertModelExists($idea);
    }

    public function test_idea_without_landing_pages_can_be_deleted(): void
    {
        $idea = $this->idea();

        $this->delete(route('ideas.destroy', $idea))
            ->assertRedirect(route('ideas.index'));

        $this->assertModelMissing($idea);
    }

    public function test_index_filters_by_status_and_name(): void
    {
        $this->idea();
        $this->idea(['name' => 'کافه‌یار', 'slug' => 'cafe', 'status' => 'testing']);

        $this->get('/ideas?status=testing')
            ->assertSee('کافه‌یار')
            ->assertDontSee('باشگاه‌یار');

        $this->get('/ideas?q=' . urlencode('باشگاه'))
            ->assertSee('باشگاه‌یار')
            ->assertDontSee('کافه‌یار');
    }
}

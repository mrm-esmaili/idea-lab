<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_user_without_role_cannot_log_in(): void
    {
        $user = User::factory()->create(['role' => null]);

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_without_role_is_logged_out_from_panel(): void
    {
        $user = User::factory()->create(['role' => null]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_admin_can_log_in(): void
    {
        $user = User::factory()->create([
            'role' => AdminRole::Support,
        ]);

        $this->post(route('login.submit'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_permissions_follow_role(): void
    {
        $finance = User::factory()->create([
            'role' => AdminRole::Finance,
        ]);

        $this->actingAs($finance)->get('/admin')->assertOk();
        $this->actingAs($finance)->get('/admin/preorders')->assertOk();
        $this->actingAs($finance)->get('/ideas')->assertForbidden();
        $this->actingAs($finance)->get('/admin/leads')->assertForbidden();

        $superAdmin = User::factory()->create([
            'role' => AdminRole::SuperAdmin,
        ]);

        $this->actingAs($superAdmin)->get('/ideas')->assertOk();
        $this->actingAs($superAdmin)->get('/admin/leads')->assertOk();
    }
}

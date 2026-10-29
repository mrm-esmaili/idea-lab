<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => AdminRole::SuperAdmin,
        ]);
    }

    public function test_only_super_admin_can_manage_users(): void
    {
        foreach ([AdminRole::Manager, AdminRole::Finance, AdminRole::Support] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->get('/admin/users')
                ->assertForbidden();
        }

        $this->actingAs($this->superAdmin)
            ->get('/admin/users')
            ->assertOk();
    }

    public function test_super_admin_creates_user(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/admin/users', [
                'name' => 'کارشناس',
                'email' => 'finance@example.com',
                'role' => 'finance',
                'password' => 'long-password-1',
                'password_confirmation' => 'long-password-1',
            ])->assertRedirect('/admin/users');

        $user = User::where('email', 'finance@example.com')->sole();
        $this->assertSame(AdminRole::Finance, $user->role);
        $this->assertTrue(Hash::check('long-password-1', $user->password));
    }

    public function test_short_password_and_duplicate_email_are_rejected(): void
    {
        $this->actingAs($this->superAdmin)
            ->post('/admin/users', [
                'name' => 'x',
                'email' => $this->superAdmin->email,
                'role' => 'support',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])->assertSessionHasErrors(['email', 'password']);
    }

    public function test_cannot_change_own_role(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('admin.users.update', $this->superAdmin), [
                'name' => $this->superAdmin->name,
                'email' => $this->superAdmin->email,
                'role' => '',
            ])->assertSessionHasErrors('role');

        $this->assertSame(AdminRole::SuperAdmin, $this->superAdmin->fresh()->role);
    }

    public function test_can_edit_own_profile_keeping_role(): void
    {
        $this->actingAs($this->superAdmin)
            ->put(route('admin.users.update', $this->superAdmin), [
                'name' => 'نام تازه',
                'email' => $this->superAdmin->email,
                'role' => 'super_admin',
            ])->assertSessionHasNoErrors();

        $this->assertSame('نام تازه', $this->superAdmin->fresh()->name);
    }

    public function test_removing_role_revokes_access_and_sessions(): void
    {
        $support = User::factory()->create(['role' => AdminRole::Support]);

        DB::table('sessions')->insert([
            'id' => 'abc',
            'user_id' => $support->id,
            'payload' => '',
            'last_activity' => time(),
        ]);

        config(['session.driver' => 'database']);

        $this->actingAs($this->superAdmin)
            ->put(route('admin.users.update', $support), [
                'name' => $support->name,
                'email' => $support->email,
                'role' => '',
            ])->assertRedirect('/admin/users');

        $this->assertNull($support->fresh()->role);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $support->id)->count());

        $this->actingAs($support->fresh())
            ->get('/admin')
            ->assertRedirect(route('login'));
    }

    public function test_password_is_kept_when_left_empty(): void
    {
        $support = User::factory()->create(['role' => AdminRole::Support]);
        $hash = $support->password;

        $this->actingAs($this->superAdmin)
            ->put(route('admin.users.update', $support), [
                'name' => $support->name,
                'email' => $support->email,
                'role' => 'manager',
                'password' => '',
                'password_confirmation' => '',
            ])->assertSessionHasNoErrors();

        $this->assertSame($hash, $support->fresh()->password);
        $this->assertSame(AdminRole::Manager, $support->fresh()->role);
    }
}

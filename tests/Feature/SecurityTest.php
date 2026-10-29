<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Idea;
use App\Models\Lead;
use App\Models\Preorder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * مرحله ۹۱: هیچ مسیر پنل بدون ورود، بررسی مدیر و مجوز مشخص نیست.
     */
    public function test_every_admin_route_requires_auth_admin_and_permission(): void
    {
        $public = ['/', 'login', 'logout', 'up', 'install', 'storage/{path}'];
        $checked = 0;

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $isAdmin = str_starts_with($uri, 'admin') || str_starts_with($uri, 'ideas') || str_starts_with($uri, 'landing-pages');

            if (! $isAdmin || in_array($uri, $public, true)) {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth:web', $middleware, "{$uri} must require admin login");
            $this->assertContains('admin', $middleware, "{$uri} must require an admin role");
            $this->assertNotEmpty(
                array_filter($middleware, fn ($m) => is_string($m) && str_starts_with($m, 'can:')),
                "{$uri} must declare a permission"
            );
            $checked++;
        }

        $this->assertGreaterThan(80, $checked);
    }

    public function test_account_routes_require_contact_guard(): void
    {
        foreach (Route::getRoutes() as $route) {
            // صفحات ورود مخاطب مهمان‌اند
            if (! str_starts_with($route->uri(), 'account') || in_array('guest:contact', $route->gatherMiddleware(), true)) {
                continue;
            }

            $this->assertContains('auth:contact', $route->gatherMiddleware(), $route->uri());
        }
    }

    public function test_csrf_is_enforced_on_state_changing_routes(): void
    {
        // storage/{path}: آپلود موقت دیسک محلی Laravel با نشانی امضاشده
        $exempt = ['install', 'storage/{path}'];

        foreach (Route::getRoutes() as $route) {
            if (array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']) === [] || in_array($route->uri(), $exempt, true)) {
                continue;
            }

            $this->assertNotContains(
                \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
                $route->excludedMiddleware(),
                $route->uri() . ' must keep CSRF protection'
            );
            $this->assertContains('web', $route->gatherMiddleware(), $route->uri());
        }
    }

    /**
     * بدون پیشوند، Laravel شمارنده محدودیت نرخ همه مسیرها را برای یک IP مشترک
     * می‌کند و مشتری پس از پرداخت هنگام ورود به حساب خطای 429 می‌گرفت.
     */
    public function test_each_rate_limited_route_has_its_own_counter(): void
    {
        $prefixes = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'throttle:')) {
                    $parts = explode(',', substr($middleware, 9));
                    $this->assertCount(3, $parts, "{$route->uri()} throttle needs its own prefix");
                    $prefixes[] = $parts[2];
                }
            }
        }

        $this->assertSame(count($prefixes), count(array_unique($prefixes)), 'throttle prefixes must be unique');

        // پر شدن سقف فرم علاقه‌مندی، ورود به حساب را مسدود نمی‌کند
        $idea = Idea::create(['name' => 'a', 'slug' => 'a', 'status' => 'testing']);
        $idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't', 'is_active' => true]);
        foreach (range(1, 6) as $i) {
            $this->post(route('leads.store', 'p'), ['form' => 'lead', 'name' => 'x', 'phone' => '0912000000' . $i]);
        }
        $this->post(route('leads.store', 'p'), ['form' => 'lead'])->assertStatus(429);
        $this->get(route('account.login'))->assertOk();
        $this->post(route('account.login.send'), ['mobile' => 'invalid'])->assertSessionHasErrors();
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get(route('login'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_public_forms_reject_bots_by_honeypot_and_timing(): void
    {
        config(['security.public_form_min_seconds' => 3]);

        $idea = Idea::create(['name' => 'a', 'slug' => 'a', 'status' => 'testing']);
        $page = $idea->landingPages()->create(['name' => 'p', 'slug' => 'p', 'title' => 't', 'is_active' => true]);
        $lead = ['form' => 'lead', 'name' => 'علی', 'phone' => '09121234567', 'consent' => '1'];
        $started = fn (int $secondsAgo) => Crypt::encryptString((string) (now()->getTimestamp() - $secondsAgo));

        // صفحه فیلدهای محافظ را دارد
        $this->get(route('landing-pages.show', 'p'))->assertSee('name="website"', false)->assertSee('name="_form_started"', false);

        // honeypot: پاسخ موفق ظاهری، بدون ثبت
        $this->post(route('leads.store', 'p'), $lead + ['website' => 'http://spam', '_form_started' => $started(10)])->assertSessionHas('lead_success', 'p');
        // خیلی سریع، بدون فیلد زمان و دست‌کاری‌شده
        $this->post(route('leads.store', 'p'), $lead + ['_form_started' => $started(1)])->assertSessionHasErrors('form', null, 'lead');
        $this->post(route('leads.store', 'p'), $lead)->assertSessionHasErrors('form', null, 'lead');
        $this->post(route('leads.store', 'p'), $lead + ['_form_started' => 'tampered'])->assertSessionHasErrors('form', null, 'lead');
        $this->post(route('preorders.store', 'p'), ['form' => 'preorder', 'name' => 'x', 'phone' => '09121234567', '_form_started' => $started(1)])->assertSessionHasErrors('form', null, 'preorder');
        $this->assertSame(0, Lead::count());
        $this->assertSame(0, Preorder::count());

        // کاربر واقعی (پس از پایان پنجره محدودیت نرخ همین IP)
        $this->travel(61)->seconds();
        $this->post(route('leads.store', 'p'), $lead + ['_form_started' => $started(8)])->assertSessionHasNoErrors('lead');
        $this->assertSame(1, Lead::count());
    }

    public function test_otp_code_is_not_flashed_to_session(): void
    {
        $this->withSession(['otp_mobile' => '09121234567'])
            ->post(route('account.verify.submit'), ['code' => '123456']);

        $this->assertNull(session()->getOldInput('code'));
    }

    public function test_installer_is_hidden_without_token_or_after_install(): void
    {
        $this->get('/install')->assertNotFound();

        config(['app.install_token' => 'install-secret-123']);
        $this->get('/install?token=wrong')->assertNotFound();
        $this->get('/install?token=install-secret-123')->assertOk()->assertSee('نصب ایده‌لب');

        $this->post('/install', ['token' => 'install-secret-123', 'name' => 'مدیر', 'email' => 'owner@example.com', 'password' => 'short', 'password_confirmation' => 'short'])
            ->assertStatus(422);
        $this->assertSame(0, User::count());

        $this->post('/install', ['token' => 'install-secret-123', 'name' => 'مدیر', 'email' => 'owner@example.com', 'password' => 'Very-strong-pass-2026', 'password_confirmation' => 'Very-strong-pass-2026'])
            ->assertOk()
            ->assertSee('INSTALL_TOKEN');

        $owner = User::sole();
        $this->assertSame(AdminRole::SuperAdmin, $owner->role);
        $this->assertTrue(password_verify('Very-strong-pass-2026', $owner->password));

        // پس از ساخت مدیر کل، نصب دوباره ممکن نیست
        $this->get('/install?token=install-secret-123')->assertNotFound();
        $this->post('/install', ['token' => 'install-secret-123', 'name' => 'x', 'email' => 'evil@example.com', 'password' => 'Very-strong-pass-2026', 'password_confirmation' => 'Very-strong-pass-2026'])->assertNotFound();
        $this->assertSame(1, User::count());
    }

    public function test_health_check_and_maintenance_actions(): void
    {
        $this->get('/up')->assertOk();

        $admin = User::factory()->create(['role' => AdminRole::SuperAdmin]);
        $this->actingAs($admin)->post(route('admin.system.migrate'))->assertSessionHas('success');
        $this->actingAs(User::factory()->create(['role' => AdminRole::Manager]))->post(route('admin.system.migrate'))->assertForbidden();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\SystemUpdate;
use App\Models\User;
use App\Services\Updates\Htaccess;
use App\Services\Updates\ReleaseBuilder;
use App\Services\Updates\UpdateException;
use App\Services\Updates\UpdateInstaller;
use App\Services\Updates\UpdatePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/**
 * سامانه به‌روزرسانی (مراحل ۱۰۱ تا ۱۰۹) روی یک پروژه نمونه در پوشه موقت؛
 * پروژه واقعی هرگز بازنویسی نمی‌شود.
 */
class UpdateSystemTest extends TestCase
{
    use RefreshDatabase;

    private const CPANEL_BLOCK = "# php -- BEGIN cPanel-generated handler, do not edit\nAddHandler application/x-httpd-ea-php84 .php .php8 .phtml\n# php -- END cPanel-generated handler, do not edit";

    private string $root;
    private string $source;
    private string $target;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir() . '/idealab-update-' . uniqid();
        $this->source = $this->root . '/source';
        $this->target = $this->root . '/target';

        $this->writeSource([
            'artisan' => "<?php // artisan\n",
            'public/index.php' => "<?php // index\n",
            'public/.htaccess' => "Require all granted\n# v1\n",
            'composer.json' => '{"require":{"php":"^8.2"}}',
            'composer.lock' => '{"packages":[]}',
            'vendor/autoload.php' => "<?php // autoload\n",
            'app/Changed.php' => "<?php // v1\n",
            'app/Removed.php' => "<?php // removed in v2\n",
            'app/Edited.php' => "<?php // removed in v2 but edited on server\n",
            'database/migrations/.gitkeep' => '',
            'storage/logs/.gitignore' => "*\n",
            'storage/logs/laravel.log' => 'secret log',
            'tests/ExampleTest.php' => '<?php',
            '.env' => 'APP_KEY=dev-secret',
            '.env.example' => 'APP_KEY=',
        ]);

        config([
            'updates.target' => $this->target,
            'updates.directory' => $this->root . '/updates',
            'updates.enabled' => true,
            'backup.directory' => $this->root . '/backups',
        ]);
    }

    protected function tearDown(): void
    {
        if (app()->isDownForMaintenance()) {
            Artisan::call('up');
        }

        File::deleteDirectory($this->root);

        parent::tearDown();
    }

    public function test_release_package_has_checksums_and_no_secrets(): void
    {
        $manifest = app(ReleaseBuilder::class)->build($this->source, '1.0.0', $this->root . '/full.zip');

        $this->assertSame('1.0.0', $manifest['version']);
        $this->assertSame('8.2.0', $manifest['min_php']);
        $this->assertTrue($manifest['vendor_included']);
        $this->assertArrayHasKey('vendor/autoload.php', $manifest['files']);
        $this->assertArrayHasKey('storage/logs/.gitignore', $manifest['files']);
        $this->assertArrayHasKey('.env.example', $manifest['files']);

        foreach (['.env', 'storage/logs/laravel.log', 'tests/ExampleTest.php'] as $excluded) {
            $this->assertArrayNotHasKey($excluded, $manifest['files']);
        }

        $package = UpdatePackage::open($this->root . '/full.zip');
        $this->assertSame("1.0.0\n", $package->content('VERSION'));

        $lite = app(ReleaseBuilder::class)->build($this->source, '1.0.1', $this->root . '/lite.zip', includeVendor: false);
        $this->assertFalse($lite['vendor_included']);
        $this->assertArrayNotHasKey('vendor/autoload.php', $lite['files']);
    }

    public function test_update_applies_changes_protects_server_data_and_rolls_back(): void
    {
        $this->installVersion('1.0.0');
        File::append($this->target . '/public/.htaccess', "\n" . self::CPANEL_BLOCK . "\n");
        File::put($this->target . '/app/Edited.php', "<?php // edited by owner\n");
        File::put($this->target . '/.env', 'APP_KEY=production-secret');
        File::ensureDirectoryExists($this->target . '/public/uploads');
        File::put($this->target . '/public/uploads/photo.jpg', 'image');

        $this->writeSource([
            'app/Changed.php' => "<?php // v2\n",
            'app/Added.php' => "<?php // new in v2\n",
            'public/.htaccess' => "Require all granted\n# v2\n",
            'storage/logs/.gitignore' => "*\n!.gitignore\n",
        ]);
        File::delete([$this->source . '/app/Removed.php', $this->source . '/app/Edited.php']);
        $package = $this->build('1.1.0');

        $installer = app(UpdateInstaller::class);
        $plan = $installer->plan($package);
        $this->assertEqualsCanonicalizing(['VERSION', 'app/Changed.php', 'public/.htaccess'], $plan['changed']);
        $this->assertSame(['app/Added.php'], $plan['added']);
        $this->assertSame(['app/Removed.php'], $plan['removed']);
        $this->assertSame(['app/Edited.php'], $plan['modified_kept']);

        $update = $installer->apply($package, null, 'cli');
        $this->assertSame(SystemUpdate::FILES_APPLIED, $update->status);
        $this->assertTrue(app()->isDownForMaintenance());

        if (in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->assertFileExists($this->root . '/backups/' . $update->backup_file);
        }

        $installer->finish($update);
        $this->assertSame(SystemUpdate::COMPLETED, $update->fresh()->status);
        $this->assertFalse(app()->isDownForMaintenance());

        $this->assertSame("<?php // v2\n", File::get($this->target . '/app/Changed.php'));
        $this->assertFileExists($this->target . '/app/Added.php');
        $this->assertFileDoesNotExist($this->target . '/app/Removed.php');
        $this->assertSame("<?php // edited by owner\n", File::get($this->target . '/app/Edited.php'));
        $this->assertSame('1.1.0', $installer->currentVersion());
        // داده سرور دست نمی‌خورد و تنظیم PHP در cPanel حفظ می‌شود
        $this->assertSame('APP_KEY=production-secret', File::get($this->target . '/.env'));
        $this->assertSame("*\n", File::get($this->target . '/storage/logs/.gitignore'));
        $this->assertFileExists($this->target . '/public/uploads/photo.jpg');
        $htaccess = File::get($this->target . '/public/.htaccess');
        $this->assertStringContainsString('# v2', $htaccess);
        $this->assertStringContainsString(self::CPANEL_BLOCK, $htaccess);

        // بازگرداندن کد به نسخه قبل
        $this->assertTrue($installer->canRollback($update->fresh()));
        $installer->rollback($update->fresh());
        $this->assertSame(SystemUpdate::ROLLED_BACK, $update->fresh()->status);
        $this->assertSame("<?php // v1\n", File::get($this->target . '/app/Changed.php'));
        $this->assertFileExists($this->target . '/app/Removed.php');
        $this->assertFileDoesNotExist($this->target . '/app/Added.php');
        $this->assertSame('1.0.0', $installer->currentVersion());
        $this->assertStringContainsString(self::CPANEL_BLOCK, File::get($this->target . '/public/.htaccess'));
    }

    public function test_failed_migration_restores_previous_files(): void
    {
        $this->installVersion('1.0.0');
        $this->writeSource([
            'app/Changed.php' => "<?php // v2\n",
            'database/migrations/2099_01_01_000000_broken_update.php' => "<?php\nreturn new class extends Illuminate\\Database\\Migrations\\Migration { public function up(): void { throw new RuntimeException('boom'); } };\n",
        ]);

        $installer = app(UpdateInstaller::class);
        $update = $installer->apply($this->build('1.1.0'), null, 'cli');

        try {
            $installer->finish($update);
            $this->fail('finish should fail');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('برگردانده شد', $e->getMessage());
        }

        $this->assertSame(SystemUpdate::FAILED, $update->fresh()->status);
        $this->assertStringContainsString('boom', $update->fresh()->error);
        $this->assertSame("<?php // v1\n", File::get($this->target . '/app/Changed.php'));
        $this->assertFileDoesNotExist($this->target . '/database/migrations/2099_01_01_000000_broken_update.php');
        $this->assertSame('1.0.0', $installer->currentVersion());
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_tampered_and_unsafe_packages_are_rejected(): void
    {
        $build = fn (string $name) => tap($this->root . "/{$name}.zip", fn ($path) => app(ReleaseBuilder::class)->build($this->source, '1.1.0', $path));

        $cases = [
            'tampered' => [fn (ZipArchive $zip) => $zip->addFromString('app/Changed.php', '<?php evil();'), 'دست‌کاری'],
            'traversal' => [fn (ZipArchive $zip) => $zip->addFromString('../evil.php', '<?php'), 'مسیر غیرمجاز'],
            'extra' => [fn (ZipArchive $zip) => $zip->addFromString('public/shell.php', '<?php'), 'خارج از فهرست'],
            'missing' => [fn (ZipArchive $zip) => $zip->deleteName('app/Changed.php'), 'ناقص'],
            'no-manifest' => [fn (ZipArchive $zip) => $zip->deleteName('manifest.json'), 'manifest'],
        ];

        foreach ($cases as $name => [$modify, $message]) {
            $path = $build($name);
            $zip = new ZipArchive;
            $zip->open($path);
            $modify($zip);
            $zip->close();

            try {
                UpdatePackage::open($path);
                $this->fail("{$name} package must be rejected");
            } catch (UpdateException $e) {
                $this->assertStringContainsString($message, $e->getMessage(), $name);
            }
        }

        File::put($this->root . '/not-a-zip.zip', 'hello');
        $this->expectException(UpdateException::class);
        UpdatePackage::open($this->root . '/not-a-zip.zip');
    }

    public function test_version_and_vendor_rules(): void
    {
        $this->installVersion('1.1.0');
        $installer = app(UpdateInstaller::class);

        foreach (['1.0.0' => 'قدیمی‌تر', '1.1.0' => 'هم‌اکنون نصب است'] as $version => $message) {
            try {
                $installer->apply($this->build($version), null, 'cli');
                $this->fail("{$version} must be blocked");
            } catch (UpdateException $e) {
                $this->assertStringContainsString($message, $e->getMessage());
            }
        }

        // نصب دوباره همان نسخه فقط با انتخاب صریح
        $update = $installer->apply($this->build('1.1.0'), null, 'cli', reinstall: true);
        $installer->finish($update);
        $this->assertSame(SystemUpdate::COMPLETED, $update->fresh()->status);

        // بسته سبک فقط با composer.lock یکسان
        File::put($this->source . '/composer.lock', '{"packages":["changed"]}');
        app(ReleaseBuilder::class)->build($this->source, '1.2.0', $this->root . '/lite.zip', includeVendor: false);

        try {
            $installer->apply(UpdatePackage::open($this->root . '/lite.zip'), null, 'cli');
            $this->fail('lite package with new dependencies must be blocked');
        } catch (UpdateException $e) {
            $this->assertStringContainsString('بسته کامل', $e->getMessage());
        }

        $this->assertSame(0, SystemUpdate::where('status', '!=', SystemUpdate::COMPLETED)->count());
    }

    /**
     * بسته سبک پس از بسته کامل: فایل‌های vendor در manifest نصب‌شده هستند ولی در
     * بسته سبک نیستند و نباید «حذف‌شده در نسخه جدید» حساب شوند.
     */
    public function test_lite_update_keeps_vendor_files(): void
    {
        $this->installVersion('1.0.0');
        $this->writeSource(['app/Changed.php' => "<?php // v2\n"]);
        app(ReleaseBuilder::class)->build($this->source, '1.1.0', $this->root . '/lite.zip', includeVendor: false);

        $installer = app(UpdateInstaller::class);
        $package = UpdatePackage::open($this->root . '/lite.zip');
        $this->assertSame([], array_values(array_filter($installer->plan($package)['removed'], fn ($path) => str_starts_with($path, 'vendor/'))));

        $installer->finish($installer->apply($package, null, 'cli'));

        $this->assertFileExists($this->target . '/vendor/autoload.php');
        $this->assertSame("<?php // v2\n", File::get($this->target . '/app/Changed.php'));
        $this->assertSame('1.1.0', $installer->currentVersion());
    }

    public function test_panel_update_requires_super_admin_and_password(): void
    {
        $this->installVersion('1.0.0');
        $this->writeSource(['app/Changed.php' => "<?php // v2\n"]);
        app(ReleaseBuilder::class)->build($this->source, '1.1.0', $this->root . '/upload.zip');

        $owner = User::factory()->create(['role' => AdminRole::SuperAdmin]);
        $manager = User::factory()->create(['role' => AdminRole::Manager]);

        $this->actingAs($manager)->get(route('admin.updates.index'))->assertForbidden();

        $this->actingAs($owner)->get(route('admin.updates.index'))->assertOk()->assertSee('1.0.0');

        $this->actingAs($owner)
            ->post(route('admin.updates.upload'), ['package' => new UploadedFile($this->root . '/upload.zip', 'release.zip', 'application/zip', null, true)])
            ->assertRedirect(route('admin.updates.review', 'idea-lab-1.1.0.zip'));

        $this->actingAs($owner)->get(route('admin.updates.review', 'idea-lab-1.1.0.zip'))
            ->assertOk()
            ->assertSee('app/Changed.php')
            ->assertSee('نصب نسخه');

        $this->actingAs($owner)->from(route('admin.updates.review', 'idea-lab-1.1.0.zip'))
            ->post(route('admin.updates.apply', 'idea-lab-1.1.0.zip'), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');
        $this->assertSame(0, SystemUpdate::count());

        $response = $this->actingAs($owner)->post(route('admin.updates.apply', 'idea-lab-1.1.0.zip'), ['password' => 'password']);
        $update = SystemUpdate::sole();
        $response->assertRedirect(route('admin.updates.show', $update))->assertSessionHas('auto_finish');

        // در حالت تعمیر، بقیه سایت 503 و مسیرهای به‌روزرسانی در دسترس است
        $this->get('/')->assertStatus(503);
        $this->actingAs($owner)->get(route('admin.updates.show', $update))->assertOk()->assertSee('تکمیل به‌روزرسانی');

        $this->actingAs($owner)->post(route('admin.updates.finish', $update))->assertSessionHas('success');
        $this->assertSame(SystemUpdate::COMPLETED, $update->fresh()->status);
        $this->assertSame($owner->id, $update->fresh()->user_id);
        $this->assertSame("<?php // v2\n", File::get($this->target . '/app/Changed.php'));
        $this->assertFalse(app()->isDownForMaintenance());

        $this->actingAs($owner)->post(route('admin.updates.rollback', $update), ['password' => 'password'])->assertSessionHas('success');
        $this->assertSame("<?php // v1\n", File::get($this->target . '/app/Changed.php'));

        // خاموش از .env
        config(['updates.enabled' => false]);
        $this->actingAs($owner)->get(route('admin.updates.index'))->assertOk()->assertSee('APP_UPDATES_ENABLED=false');
        $this->actingAs($owner)->get(route('admin.updates.review', 'idea-lab-1.1.0.zip'))->assertNotFound();
    }

    public function test_finish_command_records_manual_extraction(): void
    {
        $this->installVersion('1.1.0');

        $this->artisan('system:finish-update')->assertSuccessful();

        $update = SystemUpdate::sole();
        $this->assertSame('manual', $update->method);
        $this->assertSame('1.1.0', $update->to_version);
        $this->assertSame(SystemUpdate::COMPLETED, $update->status);
        $this->assertFileExists($this->root . '/updates/' . UpdateInstaller::INSTALLED_MANIFEST);
    }

    public function test_htaccess_merge_keeps_cpanel_php_handler(): void
    {
        $current = "old rules\n\n" . self::CPANEL_BLOCK . "\n";

        $this->assertSame("new rules\n\n" . self::CPANEL_BLOCK . "\n", Htaccess::merge("new rules\n", $current));
        $this->assertSame("new rules\n", Htaccess::merge("new rules\n", "old rules\n"));
        $this->assertSame("new rules\n", Htaccess::merge("new rules\n", null));
    }

    /**
     * نصب تازه: Extract بسته در پوشه مقصد (مثل File Manager) به‌همراه manifest نصب‌شده.
     */
    private function installVersion(string $version): void
    {
        $path = $this->root . "/install-{$version}.zip";
        app(ReleaseBuilder::class)->build($this->source, $version, $path);

        $zip = new ZipArchive;
        $zip->open($path);
        $zip->extractTo($this->target);
        $zip->close();

        File::ensureDirectoryExists($this->root . '/updates');
        File::copy($this->target . '/manifest.json', $this->root . '/updates/' . UpdateInstaller::INSTALLED_MANIFEST);
    }

    private function build(string $version): UpdatePackage
    {
        $path = $this->root . "/build-{$version}-" . uniqid() . '.zip';
        app(ReleaseBuilder::class)->build($this->source, $version, $path);

        return UpdatePackage::open($path);
    }

    private function writeSource(array $files): void
    {
        foreach ($files as $path => $content) {
            File::ensureDirectoryExists(dirname($this->source . '/' . $path));
            File::put($this->source . '/' . $path, $content);
        }
    }
}

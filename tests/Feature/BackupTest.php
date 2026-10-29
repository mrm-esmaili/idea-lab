<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Models\User;
use App\Services\Backup\DatabaseBackup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('Database backup is MySQL/MariaDB only.');
        }

        config(['backup.keep' => 2]);
        $this->app->instance(DatabaseBackup::class, new class extends DatabaseBackup
        {
            public function directory(): string
            {
                return storage_path('framework/testing/backups');
            }
        });
        File::deleteDirectory(storage_path('framework/testing/backups'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('framework/testing/backups'));

        parent::tearDown();
    }

    public function test_backup_contains_schema_and_data_and_is_protected(): void
    {
        Contact::create(['mobile' => '09121234567', 'name' => "نام با 'کوتیشن' و \\ بک‌اسلش"]);

        $path = app(DatabaseBackup::class)->create();
        $sql = gzdecode(file_get_contents($path));

        $this->assertStringContainsString('CREATE TABLE `contacts`', $sql);
        $this->assertStringContainsString('INSERT INTO `contacts`', $sql);
        $this->assertStringContainsString("'09121234567'", $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=0', $sql);
        $this->assertStringNotContainsString('INSERT INTO `sessions`', $sql);
        $this->assertFileExists(dirname($path) . '/.htaccess');

        // بازیابی در همان پایگاه داده آزمون: جداول بازسازی و داده برمی‌گردد
        Contact::query()->delete();
        DB::unprepared($sql);
        $this->assertSame("نام با 'کوتیشن' و \\ بک‌اسلش", Contact::sole()->name);

        // DDL بازیابی تراکنش آزمون را commit می‌کند؛ داده این آزمون پاک شود
        Contact::query()->delete();
    }

    public function test_panel_creates_downloads_prunes_and_audits(): void
    {
        $admin = User::factory()->create(['role' => AdminRole::SuperAdmin]);

        foreach (range(1, 3) as $i) {
            $this->travel(1)->seconds();
            $this->actingAs($admin)->post(route('admin.backups.store'))->assertSessionHas('success');
        }

        $files = app(DatabaseBackup::class)->list();
        $this->assertCount(2, $files);

        $this->actingAs($admin)->get(route('admin.backups.download', $files[0]['name']))->assertOk()->assertDownload($files[0]['name']);
        $this->actingAs($admin)->get('/admin/backups/idealab-20200101-000000.sql.gz')->assertNotFound();
        $this->actingAs($admin)->get('/admin/backups/..%2F.env')->assertNotFound();
        $this->assertSame(1, AuditLog::where('action', 'backup.downloaded')->count());

        $this->actingAs(User::factory()->create(['role' => AdminRole::Manager]))->get(route('admin.backups.index'))->assertForbidden();
    }
}

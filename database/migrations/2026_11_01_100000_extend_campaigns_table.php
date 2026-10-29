<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('status', 20)->default('planned')->after('medium')->index();
            $table->date('starts_on')->nullable()->after('status');
            $table->date('ends_on')->nullable()->after('starts_on');
            $table->unsignedBigInteger('budget_rial')->nullable()->after('ends_on');
            $table->text('notes')->nullable()->after('budget_rial');
        });

        // is_active با وضعیت کامل‌تر جایگزین می‌شود؛ تا این مرحله هیچ کدی از آن استفاده نمی‌کرد
        DB::table('campaigns')->where('is_active', true)->update(['status' => 'running']);
        DB::table('campaigns')->where('is_active', false)->update(['status' => 'paused']);

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        // انتساب به کمپین با (صفحه فرود، کد utm_campaign) انجام می‌شود
        foreach (['landing_page_events', 'leads', 'preorders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->index(['landing_page_id', 'campaign']);
            });
        }
    }

    public function down(): void
    {
        foreach (['landing_page_events', 'leads', 'preorders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['landing_page_id', 'campaign']);
            });
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('medium');
        });

        DB::table('campaigns')->update([
            'is_active' => DB::raw("CASE WHEN status = 'running' THEN 1 ELSE 0 END"),
        ]);

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'starts_on', 'ends_on', 'budget_rial', 'notes']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // null یعنی بدون دسترسی مدیریتی
            $table->string('role', 30)
                ->nullable()
                ->after('password')
                ->index();
        });

        // تا قبل از این مرحله هر کاربر واردشده مدیر بود؛
        // برای حفظ دسترسی فعلی، کاربران موجود مدیر کل می‌شوند.
        DB::table('users')->update(['role' => 'super_admin']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['role']);
            $table->dropColumn('role');
        });
    }
};

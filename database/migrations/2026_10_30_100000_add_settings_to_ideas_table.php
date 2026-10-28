<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            // همه مبالغ به ریال ذخیره و به تومان نمایش داده می‌شوند (App\Support\Money)
            $table->unsignedBigInteger('budget_rial')->nullable()->after('archived_at');
            $table->date('test_starts_on')->nullable()->after('budget_rial');
            $table->date('test_ends_on')->nullable()->after('test_starts_on');
            $table->unsignedInteger('lead_goal')->nullable()->after('test_ends_on');
            $table->unsignedInteger('paid_preorder_goal')->nullable()->after('lead_goal');

            // null یعنی پیش‌خرید برای این ایده باز نیست
            $table->unsignedBigInteger('preorder_amount_rial')->nullable()->after('paid_preorder_goal');
        });

        // پیش از این مرحله مبلغ پیش‌خرید برای همه ایده‌ها از یک متغیر محیطی
        // سراسری (به تومان) خوانده می‌شد؛ همان مبلغ برای ایده‌های موجود حفظ می‌شود.
        $legacyToman = (int) config('services.zarinpal.preorder_amount_toman', 10000);

        if ($legacyToman > 0) {
            DB::table('ideas')->update([
                'preorder_amount_rial' => $legacyToman * 10,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('ideas', function (Blueprint $table) {
            $table->dropColumn([
                'budget_rial',
                'test_starts_on',
                'test_ends_on',
                'lead_goal',
                'paid_preorder_goal',
                'preorder_amount_rial',
            ]);
        });
    }
};

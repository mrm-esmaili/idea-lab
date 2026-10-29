<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preorders', function (Blueprint $table) {
            // مبلغ در لحظه ثبت سفارش ثابت می‌شود؛ تغییر بعدی مبلغ ایده اثری ندارد
            $table->unsignedBigInteger('amount_rial')->nullable()->after('amount');

            $table->foreignId('refund_policy_id')
                ->nullable()
                ->after('amount_rial')
                ->constrained()
                ->restrictOnDelete();

            $table->timestamp('terms_accepted_at')->nullable()->after('refund_policy_id');
        });

        // ستون قدیمی amount به تومان بود؛ مقدار ریالی معادل برای سفارش‌های موجود.
        // ستون amount فعلاً حذف نمی‌شود (بازبینی در مرحله ۸۵).
        DB::table('preorders')
            ->whereNotNull('amount')
            ->update(['amount_rial' => DB::raw('amount * 10')]);
    }

    public function down(): void
    {
        Schema::table('preorders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refund_policy_id');
            $table->dropColumn(['amount_rial', 'terms_accepted_at']);
        });
    }
};

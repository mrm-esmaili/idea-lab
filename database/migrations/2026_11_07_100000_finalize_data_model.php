<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * نهایی‌سازی مدل داده (مرحله ۸۵).
 *
 * - حذف ستون قدیمی preorders.amount (تومان) پس از اطمینان از پر بودن amount_rial.
 * - داده تجاری (سرنخ، سفارش، آزمایش، هزینه) با حذف والد پاک نمی‌شود: حذف
 *   ایده، صفحه فرود یا کمپینی که چنین داده‌ای دارد در پایگاه داده هم رد می‌شود
 *   (RESTRICT به‌جای CASCADE/SET NULL). برنامه پیش از آن پیام مناسب می‌دهد.
 * - ایندکس برای پرس‌وجوهای بازه‌ای گزارش‌ها، داشبورد و جست‌وجو.
 * - مرجع واریز بازپرداخت برای هر پرداخت یکتا (پیش‌تر فقط در برنامه بررسی می‌شد).
 */
return new class extends Migration
{
    /** [جدول, ستون, جدول مرجع, رفتار جدید, رفتار قبلی] */
    private const FOREIGN_KEYS = [
        ['landing_pages', 'idea_id', 'ideas', 'restrict', 'cascade'],
        ['leads', 'landing_page_id', 'landing_pages', 'restrict', 'cascade'],
        ['preorders', 'landing_page_id', 'landing_pages', 'restrict', 'cascade'],
        ['experiments', 'idea_id', 'ideas', 'restrict', 'cascade'],
        ['experiment_campaign', 'campaign_id', 'campaigns', 'restrict', 'cascade'],
        ['expenses', 'idea_id', 'ideas', 'restrict', 'set null'],
        ['expenses', 'campaign_id', 'campaigns', 'restrict', 'set null'],
    ];

    private const INDEXES = [
        ['payments', ['status', 'verified_at']],
        ['payments', ['ref_id']],
        ['landing_page_events', ['event_name', 'occurred_at']],
        ['leads', ['created_at']],
        ['preorders', ['created_at']],
        ['sms_messages', ['created_at']],
        ['contacts', ['marketing_sms_consent']],
        ['expenses', ['incurred_on']],
    ];

    public function up(): void
    {
        DB::table('preorders')
            ->whereNull('amount_rial')
            ->whereNotNull('amount')
            ->update(['amount_rial' => DB::raw('amount * 10')]);

        Schema::table('preorders', function (Blueprint $table) {
            $table->dropColumn('amount');
        });

        foreach (self::FOREIGN_KEYS as [$table, $column, $references, $onDelete]) {
            $this->replaceForeign($table, $column, $references, $onDelete);
        }

        foreach (self::INDEXES as [$table, $columns]) {
            Schema::table($table, fn (Blueprint $t) => $t->index($columns));
        }

        Schema::table('refunds', function (Blueprint $table) {
            $table->unique(['payment_id', 'reference']);
        });
    }

    public function down(): void
    {
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropUnique(['payment_id', 'reference']);
        });

        foreach (self::INDEXES as [$table, $columns]) {
            Schema::table($table, fn (Blueprint $t) => $t->dropIndex($columns));
        }

        foreach (self::FOREIGN_KEYS as [$table, $column, $references, , $previous]) {
            $this->replaceForeign($table, $column, $references, $previous);
        }

        Schema::table('preorders', function (Blueprint $table) {
            $table->unsignedBigInteger('amount')->nullable()->after('phone');
        });

        DB::table('preorders')
            ->whereNotNull('amount_rial')
            ->update(['amount' => DB::raw('amount_rial / 10')]);
    }

    private function replaceForeign(string $table, string $column, string $references, string $onDelete): void
    {
        Schema::table($table, function (Blueprint $t) use ($column) {
            $t->dropForeign([$column]);
        });

        Schema::table($table, function (Blueprint $t) use ($column, $references, $onDelete) {
            $t->foreign($column)->references('id')->on($references)->onDelete($onDelete);
        });
    }
};

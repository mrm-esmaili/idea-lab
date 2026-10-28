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
            // وضعیت مالی سفارش؛ فقط سیستم پس از تأیید درگاه آن را تغییر می‌دهد.
            // ستون status از این پس فقط وضعیت پیگیری فروش است.
            $table->string('payment_status', 20)
                ->default('unpaid')
                ->after('status')
                ->index();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('card_pan', 32)->nullable()->after('ref_id');
            $table->string('failure_reason', 255)->nullable()->after('card_pan');
            // پاسخ درگاه بدون داده حساس (card_hash ذخیره نمی‌شود)
            $table->json('gateway_response')->nullable()->after('failure_reason');
        });

        DB::table('preorders')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('payments')
                    ->whereColumn('payments.preorder_id', 'preorders.id')
                    ->where('payments.status', 'paid');
            })
            ->update(['payment_status' => 'paid']);

        // «converted» وضعیت پرداخت بود که با وضعیت پیگیری مخلوط شده بود؛
        // پرداخت واقعی اکنون در payment_status است.
        DB::table('preorders')
            ->where('status', 'converted')
            ->update(['status' => 'interested']);
    }

    public function down(): void
    {
        DB::table('preorders')
            ->where('payment_status', 'paid')
            ->update(['status' => 'converted']);

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['card_pan', 'failure_reason', 'gateway_response']);
        });

        Schema::table('preorders', function (Blueprint $table) {
            $table->dropIndex(['payment_status']);
            $table->dropColumn('payment_status');
        });
    }
};

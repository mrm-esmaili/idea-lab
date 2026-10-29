<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // هر ردیف یک نسخه تغییرناپذیر از شرایط بازپرداخت یک ایده است.
        // سفارش به نسخه‌ای که مشتری پذیرفته ارجاع می‌دهد؛ ویرایش شرایط
        // نسخه جدید می‌سازد و سفارش‌های قبلی را تغییر نمی‌دهد.
        Schema::create('refund_policies', function (Blueprint $table) {
            $table->id();

            $table->foreignId('idea_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('version');

            $table->boolean('is_refundable');
            $table->unsignedSmallInteger('refund_window_days')->nullable();
            $table->text('eligibility')->nullable();
            $table->text('terms');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['idea_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_policies');
    }
};

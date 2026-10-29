<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // درخواست بازپرداخت؛ «درخواست»، «تصمیم مدیر» و «اجرای واقعی» سه مرحله جدا هستند
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preorder_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            // نسخه شرایطی که مشتری هنگام خرید پذیرفته بود
            $table->foreignId('refund_policy_id')->nullable()->constrained()->restrictOnDelete();

            // customer | admin
            $table->string('requested_by_type', 20);
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->unsignedBigInteger('requested_amount_rial');
            $table->text('reason');

            // pending | approved | rejected | completed
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('approved_amount_rial')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['payment_id', 'status']);
        });

        // اجرای واقعی بازپرداخت (بانکی/دستی)؛ بدون مرجع ثبت نمی‌شود
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_request_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount_rial');
            // bank_transfer | card_to_card | gateway | cash | other
            $table->string('method', 20);
            $table->string('reference', 100);
            $table->date('refunded_on');
            $table->text('note')->nullable();
            $table->foreignId('executed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index('payment_id');
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            // ads | development | design | operations | other
            $table->string('category', 20);
            // fixed | variable
            $table->string('cost_type', 10);
            $table->unsignedBigInteger('amount_rial');
            $table->date('incurred_on');
            $table->string('description', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['idea_id', 'incurred_on']);
            $table->index(['campaign_id', 'incurred_on']);
        });

        // ممیزی عملیات مهم (مالی و دیگر)؛ فقط افزودنی
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('subject_type', 100);
            $table->unsignedBigInteger('subject_id');
            $table->string('action', 50);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->json('data')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('refund_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // آزمایش: فرضیه، هدف، بازه و معیارهای موفقیت/توقف (مراحل ۶۶ و ۶۷)
        Schema::create('experiments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('idea_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('hypothesis');
            $table->text('goal');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->unsignedBigInteger('budget_rial')->nullable();
            // حداقل بازدیدکننده یکتا برای قابل‌اتکا بودن نتیجه
            $table->unsignedInteger('min_sample')->default(0);
            // [{metric, operator (gte|lte), target, kind (success|stop)}]
            $table->json('criteria');
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['idea_id', 'starts_on']);
        });

        // کمپین‌های داخل آزمایش؛ بدون کمپین یعنی همه ترافیک صفحات ایده
        Schema::create('experiment_campaign', function (Blueprint $table) {
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->primary(['experiment_id', 'campaign_id']);
        });

        // تصمیم مدیر با دلیل و snapshot شاخص‌ها، و ارزیابی پس از تصمیم (مراحل ۷۰ تا ۷۲)
        Schema::create('experiment_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('experiment_id')->constrained()->cascadeOnDelete();
            // continue | modify | stop | execute
            $table->string('decision', 20);
            $table->text('reason');
            $table->json('snapshot');
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('review_on')->nullable();
            $table->text('action_taken')->nullable();
            // better | as_expected | worse
            $table->string('outcome', 20)->nullable();
            $table->text('outcome_note')->nullable();
            $table->json('review_snapshot')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['review_on', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiment_decisions');
        Schema::dropIfExists('experiment_campaign');
        Schema::dropIfExists('experiments');
    }
};

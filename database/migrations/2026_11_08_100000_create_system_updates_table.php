<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاریخچه به‌روزرسانی‌های برنامه (مرحله ۱۰۳).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('system_updates')) {
            return;
        }

        Schema::create('system_updates', function (Blueprint $table) {
            $table->id();
            $table->string('from_version', 40)->nullable();
            $table->string('to_version', 40);
            $table->string('package')->nullable();
            // panel | cli | manual
            $table->string('method', 20);
            // applying | files_applied | completed | failed | rolled_back
            $table->string('status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('backup_file')->nullable();
            $table->json('summary')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_updates');
    }
};

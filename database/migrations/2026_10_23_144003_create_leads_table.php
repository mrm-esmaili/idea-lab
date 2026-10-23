<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();

            $table->foreignId('landing_page_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('visitor_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('name', 120);
            $table->string('phone', 30);
            $table->string('email')->nullable();

            $table->string('status', 30)->default('new');

            // منبعی که هنگام ثبت فرم به سیستم رسیده است
            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('content')->nullable();
            $table->string('term')->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('consented_at')->nullable();

            $table->timestamps();

            $table->index(['landing_page_id', 'created_at']);
            $table->index(['status', 'created_at']);
            $table->index('phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};

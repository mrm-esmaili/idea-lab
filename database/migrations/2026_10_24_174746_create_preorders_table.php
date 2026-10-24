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
        Schema::create('preorders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('landing_page_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('visitor_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('lead_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('name', 120);
            $table->string('phone', 30);

            $table->unsignedBigInteger('amount')->nullable();

            $table->string('status', 30)->default('requested');

            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();

            $table->timestamp('consented_at')->nullable();

            $table->timestamps();

            $table->index(['landing_page_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('preorders');
    }
};

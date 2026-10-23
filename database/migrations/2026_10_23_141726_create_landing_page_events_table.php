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
        Schema::create('landing_page_events', function (Blueprint $table) {
            $table->id();

            $table->foreignId('landing_page_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('visitor_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('event_name', 50);

            $table->string('source')->nullable();
            $table->string('medium')->nullable();
            $table->string('campaign')->nullable();
            $table->string('content')->nullable();
            $table->string('term')->nullable();

            $table->string('referrer_host')->nullable();

            $table->timestamp('occurred_at');

            $table->timestamps();

            $table->index([
                'landing_page_id',
                'event_name',
                'occurred_at',
            ]);

            $table->index([
                'visitor_id',
                'occurred_at',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landing_page_events');
    }
};

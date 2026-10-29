<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idea_status_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('idea_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('from_status', 30);
            $table->string('to_status', 30);

            $table->text('note')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['idea_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idea_status_changes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('preorder_id')
                ->constrained()
                ->restrictOnDelete();

            $table->string('gateway', 30)->default('zarinpal');

            $table->string('authority', 100)
                ->nullable()
                ->unique();

            $table->unsignedBigInteger('amount_rial');

            $table->string('status', 20)->default('pending');

            $table->string('ref_id', 100)->nullable();

            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            $table->index(['preorder_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

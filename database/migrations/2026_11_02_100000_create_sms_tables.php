<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // قالب‌های پیامک؛ شناسه قالب و نام متغیرها در پنل SMS.ir تعریف و اینجا وارد می‌شود
        Schema::create('sms_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name');
            // otp | transactional | promotional
            $table->string('category', 20);
            $table->unsignedBigInteger('provider_template_id')->nullable();
            // نگاشت متغیر داخلی سیستم به نام پارامتر در قالب SMS.ir
            $table->json('parameters');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // سابقه ارسال؛ مقدار پارامترهای محرمانه (مثل کد OTP) ذخیره نمی‌شود
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 20)->index();
            $table->string('template_key', 50)->nullable();
            $table->string('category', 20);
            $table->string('provider', 20);
            $table->string('status', 20);
            $table->string('provider_message_id', 100)->nullable();
            $table->decimal('cost', 10, 2)->nullable();
            $table->json('parameters')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamps();

            $table->index(['template_key', 'created_at']);
        });

        DB::table('sms_templates')->insert([
            'key' => 'otp',
            'name' => 'کد ورود مخاطب',
            'category' => 'otp',
            'provider_template_id' => null,
            'parameters' => json_encode(['code' => 'CODE']),
            'description' => 'کد یک‌بارمصرف ورود به حساب مخاطب.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_messages');
        Schema::dropIfExists('sms_templates');
    }
};

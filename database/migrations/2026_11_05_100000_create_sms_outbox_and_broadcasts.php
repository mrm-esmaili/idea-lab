<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_templates', function (Blueprint $table) {
            // متن قالب همان‌طور که در SMS.ir ثبت شده؛ برای پیش‌نمایش و مرجع
            $table->text('body')->nullable()->after('parameters');
            // اطلاع‌رسانی‌ها تا تنظیم شناسه قالب و فعال‌سازی ارسال نمی‌شوند
            $table->boolean('enabled')->default(true)->after('body');
        });

        // ارسال گروهی تبلیغاتی؛ فقط به مخاطبان دارای رضایت
        Schema::create('sms_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            // group | rules
            $table->string('audience', 20);
            $table->foreignId('contact_group_id')->nullable()->constrained()->nullOnDelete();
            $table->json('rules')->nullable();
            // مخاطبی که در این چند ساعت پیامک تبلیغاتی گرفته دوباره دریافت نمی‌کند
            $table->unsignedSmallInteger('frequency_cap_hours')->default(24);
            // draft | sending | completed | cancelled
            $table->string('status', 20)->default('draft');
            $table->unsignedInteger('recipients_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->foreignId('contact_id')->nullable()->after('mobile')->constrained()->nullOnDelete();
            $table->foreignId('broadcast_id')->nullable()->after('contact_id')->constrained('sms_broadcasts')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->after('broadcast_id')->constrained()->nullOnDelete();
            // template (قالب‌دار) | text (متن آزاد از خط اختصاصی)
            $table->string('type', 10)->default('template')->after('template_key');
            $table->text('body')->nullable()->after('type');
            // مقدار متغیرهای داخلی برای ارسال در صف؛ نگاشت به پارامتر SMS.ir هنگام ارسال
            $table->json('variables')->nullable()->after('parameters');
            // جلوگیری از پیام تکراری (مثلاً payment.paid:12)
            $table->string('dedupe_key', 150)->nullable()->unique()->after('variables');
            $table->unsignedTinyInteger('attempts')->default(0)->after('status');
            $table->timestamp('available_at')->nullable()->after('attempts');
            $table->string('claim', 36)->nullable()->index()->after('available_at');
            $table->json('response')->nullable()->after('cost');
            $table->timestamp('sent_at')->nullable()->after('error');

            $table->index(['status', 'available_at']);
        });

        $now = now();

        DB::table('sms_templates')->where('key', 'otp')->update([
            'body' => "کد ورود شما به ایده‌لب: #CODE#\nاین کد ۲ دقیقه اعتبار دارد.",
        ]);

        // قالب‌های اطلاع‌رسانی: غیرفعال تا شناسه قالب SMS.ir وارد شود
        $templates = [
            ['payment_paid', 'پرداخت موفق پیش‌خرید', ['name' => 'NAME', 'idea' => 'IDEA', 'amount' => 'AMOUNT', 'ref_id' => 'REF'],
                "#NAME# عزیز، پرداخت #AMOUNT# تومان برای پیش‌خرید #IDEA# با کد پیگیری #REF# ثبت شد.",
                'پس از تأیید قطعی پرداخت توسط درگاه (نه صرفاً بازگشت مرورگر) ارسال می‌شود.'],
            ['refund_requested', 'دریافت درخواست بازپرداخت', ['name' => 'NAME', 'idea' => 'IDEA', 'amount' => 'AMOUNT'],
                "#NAME# عزیز، درخواست بازپرداخت #AMOUNT# تومان برای #IDEA# دریافت شد و بررسی می‌شود.",
                'پس از ثبت درخواست بازپرداخت (توسط مشتری یا پشتیبانی).'],
            ['refund_approved', 'تأیید بازپرداخت', ['name' => 'NAME', 'idea' => 'IDEA', 'amount' => 'AMOUNT'],
                "#NAME# عزیز، بازپرداخت #AMOUNT# تومان برای #IDEA# تأیید شد و به‌زودی واریز می‌شود.",
                'پس از تأیید مدیر؛ هنوز وجهی واریز نشده است.'],
            ['refund_rejected', 'رد درخواست بازپرداخت', ['name' => 'NAME', 'idea' => 'IDEA'],
                "#NAME# عزیز، درخواست بازپرداخت شما برای #IDEA# پذیرفته نشد. جزئیات در حساب کاربری شما.",
                'پس از رد درخواست توسط مدیر.'],
            ['refund_executed', 'واریز بازپرداخت', ['name' => 'NAME', 'idea' => 'IDEA', 'amount' => 'AMOUNT', 'reference' => 'REF'],
                "#NAME# عزیز، مبلغ #AMOUNT# تومان بابت بازپرداخت #IDEA# با شماره پیگیری #REF# واریز شد.",
                'پس از ثبت هر اجرای واقعی بازپرداخت.'],
            ['idea_validated', 'موفقیت آزمون ایده', ['name' => 'NAME', 'idea' => 'IDEA'],
                "#NAME# عزیز، آزمون #IDEA# با موفقیت تمام شد و ساخت آن آغاز می‌شود.",
                'به خریداران پیش‌خرید، هنگام تغییر وضعیت ایده به «تأییدشده» (با انتخاب مدیر).'],
            ['idea_rejected', 'توقف ایده', ['name' => 'NAME', 'idea' => 'IDEA'],
                "#NAME# عزیز، متأسفانه #IDEA# متوقف شد. برای بازپرداخت به حساب کاربری خود مراجعه کنید.",
                'به خریداران پیش‌خرید، هنگام تغییر وضعیت ایده به «ردشده» (با انتخاب مدیر).'],
            ['idea_launched', 'آماده‌شدن محصول', ['name' => 'NAME', 'idea' => 'IDEA'],
                "#NAME# عزیز، #IDEA# آماده شد! جزئیات تحویل به‌زودی برای شما ارسال می‌شود.",
                'به خریداران پیش‌خرید، هنگام تغییر وضعیت ایده به «عرضه‌شده» (با انتخاب مدیر).'],
        ];

        foreach ($templates as [$key, $name, $parameters, $body, $description]) {
            DB::table('sms_templates')->insert([
                'key' => $key,
                'name' => $name,
                'category' => 'transactional',
                'provider_template_id' => null,
                'parameters' => json_encode($parameters),
                'body' => $body,
                'enabled' => false,
                'description' => $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('key', '!=', 'otp')->delete();

        Schema::table('sms_messages', function (Blueprint $table) {
            $table->dropIndex(['status', 'available_at']);
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('broadcast_id');
            $table->dropConstrainedForeignId('user_id');
            $table->dropUnique(['dedupe_key']);
            $table->dropIndex(['claim']);
            $table->dropColumn(['type', 'body', 'variables', 'dedupe_key', 'attempts', 'available_at', 'claim', 'response', 'sent_at']);
        });

        Schema::dropIfExists('sms_broadcasts');

        Schema::table('sms_templates', function (Blueprint $table) {
            $table->dropColumn(['body', 'enabled']);
        });
    }
};

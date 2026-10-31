<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «فقط خبرم کن» (مراحل ۱۳۰ و ۱۳۲): نوع درخواست سرنخ و قالب پیامک آمادگی
 * برای درخواست‌کنندگان. سرنخ‌های موجود «ثبت علاقه» می‌مانند.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'intent')) {
            Schema::table('leads', function (Blueprint $table) {
                // interest | notify_when_ready
                $table->string('intent', 30)->default('interest')->after('status');
                $table->index(['intent', 'created_at']);
            });
        }

        if (! DB::table('sms_templates')->where('key', 'idea_launched_notify')->exists()) {
            DB::table('sms_templates')->insert([
                'key' => 'idea_launched_notify',
                'name' => 'آماده‌شدن محصول (درخواست‌کنندگان «خبرم کن»)',
                'category' => 'transactional',
                'provider_template_id' => null,
                'parameters' => json_encode(['name' => 'NAME', 'idea' => 'IDEA', 'link' => '']),
                'body' => "#NAME# عزیز، #IDEA# که خواسته بودید خبرتان کنیم آماده شد.",
                'enabled' => false,
                'description' => 'به کسانی که «وقتی آماده شد خبرم کن» را زده‌اند، هنگام تغییر وضعیت ایده به «عرضه‌شده» (با انتخاب مدیر)؛ فقط یک بار برای هر شماره و ایده. خریداران این پیام را نمی‌گیرند.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('sms_templates')->where('key', 'idea_launched_notify')->delete();

        if (Schema::hasColumn('leads', 'intent')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropIndex(['intent', 'created_at']);
                $table->dropColumn('intent');
            });
        }
    }
};

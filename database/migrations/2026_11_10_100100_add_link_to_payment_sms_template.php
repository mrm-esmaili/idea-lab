<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * متغیر اختیاری «لینک پیگیری» برای پیامک پرداخت موفق (مرحله ۱۱۷). نام پارامتر
 * خالی است تا قالب‌های فعال فعلی در SMS.ir بدون تغییر کار کنند.
 */
return new class extends Migration
{
    public function up(): void
    {
        $template = DB::table('sms_templates')->where('key', 'payment_paid')->first();

        if (! $template) {
            return;
        }

        $parameters = json_decode($template->parameters, true) ?: [];

        if (! array_key_exists('link', $parameters)) {
            $parameters['link'] = '';
            DB::table('sms_templates')->where('key', 'payment_paid')->update(['parameters' => json_encode($parameters)]);
        }
    }

    public function down(): void
    {
        $template = DB::table('sms_templates')->where('key', 'payment_paid')->first();

        if ($template) {
            $parameters = json_decode($template->parameters, true) ?: [];
            unset($parameters['link']);
            DB::table('sms_templates')->where('key', 'payment_paid')->update(['parameters' => json_encode($parameters)]);
        }
    }
};

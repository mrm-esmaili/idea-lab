<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // مخاطب عمومی؛ جدا از users (مدیران). شناسه یکتا: موبایل نرمال‌شده
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('mobile', 11)->unique();
            $table->string('name', 120)->nullable();
            $table->string('email')->nullable();

            // رضایت پیامک تبلیغاتی جدا از علاقه به ایده و پیام‌های تراکنشی
            $table->boolean('marketing_sms_consent')->default(false);
            $table->timestamp('marketing_consent_at')->nullable();
            $table->string('marketing_consent_source', 50)->nullable();
            $table->timestamp('marketing_opted_out_at')->nullable();

            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });

        foreach (['leads', 'preorders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('contact_id')
                    ->nullable()
                    ->after('visitor_id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        $this->backfill();
    }

    /**
     * ساخت مخاطب برای شماره‌های معتبر سرنخ‌ها و پیش‌خریدهای موجود و اتصال آن‌ها.
     */
    private function backfill(): void
    {
        foreach (['leads', 'preorders'] as $tableName) {
            DB::table($tableName)
                ->select(['id', 'phone', 'name', 'created_at'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($tableName) {
                    foreach ($rows as $row) {
                        $mobile = PhoneNumber::normalizeMobile($row->phone);

                        if ($mobile === null) {
                            continue;
                        }

                        $contactId = DB::table('contacts')->where('mobile', $mobile)->value('id');

                        if (! $contactId) {
                            $contactId = DB::table('contacts')->insertGetId([
                                'mobile' => $mobile,
                                'name' => $row->name,
                                'created_at' => $row->created_at,
                                'updated_at' => now(),
                            ]);
                        }

                        DB::table($tableName)->where('id', $row->id)->update(['contact_id' => $contactId]);
                    }
                });
        }
    }

    public function down(): void
    {
        foreach (['leads', 'preorders'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('contact_id');
            });
        }

        Schema::dropIfExists('contacts');
    }
};

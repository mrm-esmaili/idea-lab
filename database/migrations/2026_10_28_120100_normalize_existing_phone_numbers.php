<?php

use App\Support\PhoneNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['leads', 'preorders'] as $table) {
            DB::table($table)
                ->select(['id', 'phone'])
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        $normalized = PhoneNumber::normalizeMobile($row->phone);

                        // شماره نامعتبر دست‌نخورده می‌ماند تا داده از بین نرود
                        if ($normalized !== null && $normalized !== $row->phone) {
                            DB::table($table)
                                ->where('id', $row->id)
                                ->update(['phone' => $normalized]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        // مقدار خام قبلی ذخیره نشده است؛ بازگشت ممکن نیست و لازم هم نیست.
    }
};

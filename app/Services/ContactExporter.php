<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\DataExport;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * خروجی CSV مخاطبان (مرحله ۴۰).
 *
 * - فقط ستون‌های لازم برای پیگیری و ارسال پیامک (حداقل‌سازی داده)؛ ایمیل،
 *   یادداشت‌ها و جزئیات پرداخت خارج نمی‌شود.
 * - هر خروجی با کاربر، دامنه، فیلترها و تعداد ردیف در data_exports ثبت می‌شود.
 * - مقدارهای شروع‌شونده با = + - @ خنثی می‌شوند (CSV injection در Excel).
 * - UTF-8 با BOM تا فارسی در Excel درست نمایش داده شود.
 */
class ContactExporter
{
    public function download(
        Builder $query,
        string $scope,
        array $filters,
        Request $request,
        bool $consentedOnly,
    ): StreamedResponse {
        if ($consentedOnly) {
            $query->where('contacts.marketing_sms_consent', true);
        }

        $query->with('interests:id,name')->orderBy('contacts.id');

        DataExport::create([
            'user_id' => $request->user()->id,
            'scope' => $scope,
            'filters' => $filters + ['consented_only' => $consentedOnly],
            'row_count' => (clone $query)->count(),
            'ip' => $request->ip(),
        ]);

        $filename = 'contacts-' . now()->timezone(config('app.display_timezone'))->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['موبایل', 'نام', 'رضایت پیامک تبلیغاتی', 'ایده‌های مورد علاقه', 'منبع جذب', 'کمپین جذب', 'تاریخ ثبت']);

            $query->chunk(500, function ($contacts) use ($out) {
                foreach ($contacts as $contact) {
                    /** @var Contact $contact */
                    fputcsv($out, array_map([$this, 'safe'], [
                        $contact->mobile,
                        $contact->name,
                        $contact->marketing_sms_consent ? 'بله' : 'خیر',
                        $contact->interests->pluck('name')->implode('، '),
                        $contact->first_source,
                        $contact->first_campaign,
                        Format::datetime($contact->created_at, 'Y-m-d'),
                    ]));
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function safe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'" . $value : $value;
    }
}

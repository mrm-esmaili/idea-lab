<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\AuditLogger;
use App\Services\Sms\SmsService;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * سابقه پیامک (مرحله ۶۴)، تلاش دوباره دستی و ارسال دستی به یک مخاطب (مرحله ۶۳).
 */
class SmsMessageController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(config('statuses.sms_message')))],
            'category' => ['nullable', Rule::in(array_keys(config('statuses.sms_category')))],
            'template' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'broadcast' => ['nullable', 'integer'],
        ]);

        $query = SmsMessage::with(['contact:id,name', 'broadcast:id,title', 'user:id,name'])->latest('id');

        foreach (['status' => 'status', 'category' => 'category', 'template' => 'template_key', 'broadcast' => 'broadcast_id'] as $key => $column) {
            if (! empty($filters[$key])) {
                $query->where($column, $filters[$key]);
            }
        }

        if (! empty($filters['mobile'])) {
            $query->where('mobile', 'like', '%' . (PhoneNumber::normalizeMobile($filters['mobile']) ?? $filters['mobile']) . '%');
        }

        $summary = (clone $query)->reorder()
            ->selectRaw('status, COUNT(*) AS total, SUM(cost) AS cost')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('admin.sms.messages.index', [
            'messages' => $query->paginate(50)->withQueryString(),
            'summary' => $summary,
            'templateKeys' => SmsTemplate::orderBy('id')->pluck('name', 'key'),
            'filters' => $filters,
        ]);
    }

    /**
     * فقط پیامک تراکنشی ناموفق؛ کد ورود منقضی شده و ارسال گروهی تکرار نمی‌شود.
     */
    public function retry(SmsMessage $message, SmsService $sms)
    {
        if ($message->status !== SmsMessage::STATUS_FAILED || $message->category !== 'transactional') {
            return back()->withErrors(['sms' => 'فقط پیامک تراکنشی ناموفق قابل ارسال دوباره است.']);
        }

        $updated = SmsMessage::whereKey($message->id)
            ->where('status', SmsMessage::STATUS_FAILED)
            ->update(['status' => SmsMessage::STATUS_QUEUED, 'available_at' => now(), 'updated_at' => now()]);

        if ($updated) {
            $sms->dispatchSoon();
        }

        return back()->with('success', 'پیامک دوباره در صف ارسال قرار گرفت.');
    }

    public function sendToContact(Request $request, Contact $contact, SmsService $sms, AuditLogger $audit)
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:300'],
        ], [], ['body' => 'متن پیام']);

        if (config('services.sms.driver') === 'smsir' && blank(config('services.sms.smsir.line_number'))) {
            return back()->withErrors(['sms_body' => 'خط اختصاصی SMS.ir (SMSIR_LINE_NUMBER) تنظیم نشده است.']);
        }

        // پیام خدماتی به یک مخاطب (پیگیری سفارش، پاسخ پشتیبانی)؛ ارسال تبلیغاتی فقط از ارسال گروهی
        $message = $sms->queueText($contact->mobile, trim($data['body']), 'transactional', null, [
            'contact_id' => $contact->id,
            'user_id' => $request->user()->id,
        ]);

        $audit->log($contact, 'sms.manual_sent', ['sms_message_id' => $message?->id, 'body' => trim($data['body'])], $request->user());

        return back()->with('success', 'پیامک در صف ارسال قرار گرفت.');
    }
}

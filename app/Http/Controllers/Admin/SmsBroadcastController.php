<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use App\Models\Idea;
use App\Models\SmsBroadcast;
use App\Models\SmsMessage;
use App\Services\ContactSegment;
use App\Services\Sms\BroadcastService;
use App\Services\Sms\SmsDispatcher;
use App\Support\SmsText;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * ارسال گروهی پیامک تبلیغاتی: پیش‌نویس ← پیش‌نمایش گیرندگان ← تأیید نهایی ← ارسال دسته‌ای.
 */
class SmsBroadcastController extends Controller
{
    public function index()
    {
        return view('admin.sms.broadcasts.index', [
            'broadcasts' => SmsBroadcast::with(['group', 'creator'])->latest('id')->paginate(20),
        ]);
    }

    public function create(Request $request)
    {
        $broadcast = new SmsBroadcast([
            'audience' => $request->filled('group') ? 'group' : 'rules',
            'contact_group_id' => $request->integer('group') ?: null,
            'rules' => ContactSegment::clean($request->only(ContactSegment::KEYS)),
            'frequency_cap_hours' => 24,
        ]);

        return view('admin.sms.broadcasts.create', $this->formData($broadcast));
    }

    public function store(Request $request)
    {
        $footer = trim((string) config('sms.promo_footer'));
        $maxBody = (int) config('sms.max_text_length') - mb_strlen($footer) - 1;

        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', "max:{$maxBody}"],
            'audience' => ['required', Rule::in(['group', 'rules'])],
            'contact_group_id' => ['nullable', 'required_if:audience,group', 'integer', 'exists:contact_groups,id'],
            'frequency_cap_hours' => ['required', 'integer', 'min:0', 'max:720'],
            'rules' => ['nullable', 'array'],
        ] + collect(ContactSegment::validationRules())
            ->except('q')
            ->mapWithKeys(fn ($rule, $key) => ["rules.{$key}" => $rule])
            ->all(), [], [
                'title' => 'عنوان',
                'body' => 'متن پیام',
                'contact_group_id' => 'گروه',
                'frequency_cap_hours' => 'فاصله حداقل',
            ]);

        $rules = $data['audience'] === 'rules'
            ? ContactSegment::clean(array_diff_key($data['rules'] ?? [], ['q' => true]))
            : null;

        $broadcast = SmsBroadcast::create([
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $data['audience'],
            'contact_group_id' => $data['audience'] === 'group' ? $data['contact_group_id'] : null,
            'rules' => $rules,
            'frequency_cap_hours' => $data['frequency_cap_hours'],
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.sms.broadcasts.show', $broadcast)
            ->with('success', 'پیش‌نویس ذخیره شد. گیرندگان و متن را بررسی و ارسال را تأیید کنید.');
    }

    public function show(SmsBroadcast $broadcast, BroadcastService $service, ContactSegment $segment)
    {
        $broadcast->load(['group', 'creator', 'confirmer']);
        $text = $service->text($broadcast);

        $progress = $broadcast->messages()
            ->selectRaw('status, COUNT(*) AS total, SUM(cost) AS cost')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('admin.sms.broadcasts.show', [
            'broadcast' => $broadcast,
            'text' => $text,
            'parts' => SmsText::parts($text),
            'preview' => $broadcast->isDraft() ? $service->preview($broadcast) : null,
            'rulesDescription' => $broadcast->rules
                ? $segment->describe($broadcast->rules, Idea::pluck('name', 'id')->all())
                : [],
            'progress' => $progress,
            'failures' => $broadcast->messages()->where('status', SmsMessage::STATUS_FAILED)->latest('id')->limit(10)->get(),
            'lineSet' => config('services.sms.driver') !== 'smsir' || filled(config('services.sms.smsir.line_number')),
            'promoWindowOpen' => app(SmsDispatcher::class)->promoWindowOpen(),
            'lastDispatch' => Cache::get(SmsDispatcher::LAST_RUN_CACHE_KEY),
        ]);
    }

    public function confirm(Request $request, SmsBroadcast $broadcast, BroadcastService $service)
    {
        $data = $request->validate([
            'expected' => ['required', 'integer', 'min:0'],
            'confirm' => ['accepted'],
        ], ['confirm.accepted' => 'تأیید نهایی ارسال را علامت بزنید.']);

        if (config('services.sms.driver') === 'smsir' && blank(config('services.sms.smsir.line_number'))) {
            return back()->withErrors(['broadcast' => 'خط اختصاصی SMS.ir (SMSIR_LINE_NUMBER) تنظیم نشده است.']);
        }

        try {
            $count = $service->confirm($broadcast, (int) $data['expected'], $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['broadcast' => $e->getMessage()]);
        }

        return redirect()->route('admin.sms.broadcasts.show', $broadcast)
            ->with('success', number_format($count) . ' پیامک در صف ارسال قرار گرفت.');
    }

    public function cancel(Request $request, SmsBroadcast $broadcast, BroadcastService $service)
    {
        try {
            $cancelled = $service->cancel($broadcast, $request->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['broadcast' => $e->getMessage()]);
        }

        return redirect()->route('admin.sms.broadcasts.show', $broadcast)
            ->with('success', 'ارسال لغو شد' . ($cancelled ? ' و ' . number_format($cancelled) . ' پیام از صف خارج شد.' : '.'));
    }

    /**
     * ادامه ارسال یک دسته از صف؛ برای هاستی که Cron ندارد.
     */
    public function dispatchBatch(SmsBroadcast $broadcast, SmsDispatcher $dispatcher)
    {
        $stats = $dispatcher->run();

        return redirect()->route('admin.sms.broadcasts.show', $broadcast)
            ->with('success', "پردازش صف: {$stats['sent']} ارسال، {$stats['failed']} ناموفق، {$stats['retry']} برای تلاش دوباره، {$stats['skipped']} کنار گذاشته شد.");
    }

    public function destroy(SmsBroadcast $broadcast)
    {
        abort_unless($broadcast->isDraft(), 422, 'فقط پیش‌نویس قابل حذف است.');

        $broadcast->delete();

        return redirect()->route('admin.sms.broadcasts.index')->with('success', 'پیش‌نویس حذف شد.');
    }

    private function formData(SmsBroadcast $broadcast): array
    {
        return [
            'broadcast' => $broadcast,
            'groups' => ContactGroup::orderBy('name')->get(['id', 'name', 'type']),
            'ideas' => Idea::orderBy('name')->get(['id', 'name']),
            'footer' => trim((string) config('sms.promo_footer')),
            'maxBody' => (int) config('sms.max_text_length') - mb_strlen(trim((string) config('sms.promo_footer'))) - 1,
        ];
    }
}

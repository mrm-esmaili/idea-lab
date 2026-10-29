<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use App\Services\Sms\SmsDispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * کلید و متغیرهای هر قالب را سیستم تعریف می‌کند؛ مدیر شناسه قالب SMS.ir،
 * نام پارامتر متناظر هر متغیر، متن مرجع (برای پیش‌نمایش) و فعال بودن
 * اطلاع‌رسانی را تعیین می‌کند (مرحله ۵۸).
 */
class SmsTemplateController extends Controller
{
    /** مقادیر نمونه برای پیش‌نمایش متن قالب */
    public const SAMPLES = [
        'code' => '48213',
        'name' => 'سارا',
        'idea' => 'باشگاه‌یار',
        'amount' => '1,200,000',
        'ref_id' => '201453',
        'reference' => '7781-2290',
    ];

    public function index()
    {
        $templates = SmsTemplate::orderBy('id')->get()->groupBy('category');

        return view('admin.sms.index', [
            'templates' => $templates,
            'queue' => [
                'queued' => SmsMessage::where('status', SmsMessage::STATUS_QUEUED)->count(),
                'failed_24h' => SmsMessage::where('status', SmsMessage::STATUS_FAILED)->where('created_at', '>=', now()->subDay())->count(),
                'last_dispatch' => Cache::get(SmsDispatcher::LAST_RUN_CACHE_KEY),
            ],
            'lineSet' => filled(config('services.sms.smsir.line_number')),
            'driver' => config('services.sms.driver'),
            'apiKeySet' => filled(config('services.sms.smsir.api_key')),
        ]);
    }

    public function edit(SmsTemplate $template)
    {
        return view('admin.sms.edit', [
            'template' => $template,
            'samples' => self::SAMPLES,
        ]);
    }

    public function update(Request $request, SmsTemplate $template)
    {
        $rules = [
            'provider_template_id' => ['nullable', 'integer', 'min:1', 'max:9999999999', 'required_if_accepted:enabled'],
            'body' => ['nullable', 'string', 'max:1000'],
            'enabled' => ['nullable', 'boolean'],
        ];

        foreach (array_keys($template->parameters) as $variable) {
            $rules["parameters.{$variable}"] = ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/'];
        }

        $data = $request->validate($rules, [
            'parameters.*.regex' => 'نام پارامتر فقط حروف انگلیسی، عدد و زیرخط باشد.',
            'parameters.*.required' => 'نام پارامتر الزامی است.',
            'provider_template_id.required_if_accepted' => 'برای فعال‌سازی، شناسه قالب SMS.ir را وارد کنید.',
        ]);

        $template->update([
            'provider_template_id' => $data['provider_template_id'] ?? null,
            'body' => $data['body'] ?? null,
            // کد ورود همیشه فعال است؛ فقط اطلاع‌رسانی‌ها قابل خاموش شدن‌اند
            'enabled' => $template->category === 'otp' ? true : $request->boolean('enabled'),
            'parameters' => array_combine(
                array_keys($template->parameters),
                array_map(fn ($variable) => $data['parameters'][$variable], array_keys($template->parameters))
            ),
        ]);

        return redirect()
            ->route('admin.sms.index')
            ->with('success', "قالب «{$template->name}» ذخیره شد.");
    }
}

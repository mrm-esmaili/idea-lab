<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Models\SmsTemplate;
use Illuminate\Http\Request;

/**
 * کلید و متغیرهای هر قالب را سیستم تعریف می‌کند؛ مدیر فقط شناسه قالب
 * SMS.ir و نام پارامتر متناظر هر متغیر را تعیین می‌کند.
 */
class SmsTemplateController extends Controller
{
    public function index()
    {
        $templates = SmsTemplate::orderBy('id')->get();

        $messages = SmsMessage::latest('id')->limit(30)->get();

        return view('admin.sms.index', [
            'templates' => $templates,
            'messages' => $messages,
            'driver' => config('services.sms.driver'),
            'apiKeySet' => filled(config('services.sms.smsir.api_key')),
        ]);
    }

    public function edit(SmsTemplate $template)
    {
        return view('admin.sms.edit', compact('template'));
    }

    public function update(Request $request, SmsTemplate $template)
    {
        $rules = [
            'provider_template_id' => ['nullable', 'integer', 'min:1', 'max:9999999999'],
        ];

        foreach (array_keys($template->parameters) as $variable) {
            $rules["parameters.{$variable}"] = ['required', 'string', 'max:50', 'regex:/^[A-Za-z0-9_]+$/'];
        }

        $data = $request->validate($rules, [
            'parameters.*.regex' => 'نام پارامتر فقط حروف انگلیسی، عدد و زیرخط باشد.',
            'parameters.*.required' => 'نام پارامتر الزامی است.',
        ]);

        $template->update([
            'provider_template_id' => $data['provider_template_id'] ?? null,
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

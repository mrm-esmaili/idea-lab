<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\SiteSettings;
use App\Support\ContactChannel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * تنظیمات سایت (مراحل ۱۱۱ تا ۱۱۴): هویت شرکت، نماد اعتماد و راه‌های پشتیبانی
 * که در فوتر صفحات فرود و حساب مشتری نمایش داده می‌شوند.
 */
class SiteSettingsController extends Controller
{
    public const MAX_CHANNELS = 6;

    public function edit(SiteSettings $settings)
    {
        $channels = array_map(fn ($channel) => $channel->toArray(), $settings->supportChannels());

        return view('admin.settings.site', [
            'company' => $settings->get('company'),
            'logoUrl' => $settings->logoUrl(),
            'trustSeal' => $settings->get('trust_seal'),
            'support' => $settings->get('support'),
            'channels' => array_pad($channels, self::MAX_CHANNELS, ['type' => '', 'value' => '', 'label' => null, 'display' => null]),
            'enamad' => $settings->enamad(),
        ]);
    }

    public function update(Request $request, SiteSettings $settings, AuditLogger $audit)
    {
        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:120'],
            'company_website' => ['nullable', 'url:https,http', 'max:255'],
            'company_intro' => ['nullable', 'string', 'max:500'],
            'company_logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024', 'dimensions:max_width=2000,max_height=2000'],
            'remove_logo' => ['nullable', 'boolean'],
            'enamad_id' => ['nullable', 'regex:/^\d{3,12}$/', 'required_with:enamad_code'],
            'enamad_code' => ['nullable', 'regex:/^[A-Za-z0-9]{4,64}$/', 'required_with:enamad_id'],
            'support_help_text' => ['nullable', 'string', 'max:300'],
            'channels' => ['nullable', 'array', 'max:' . self::MAX_CHANNELS],
            'channels.*.type' => ['nullable', Rule::in(array_keys(ContactChannel::TYPES))],
            'channels.*.value' => ['nullable', 'string', 'max:120', 'required_with:channels.*.type'],
            'channels.*.label' => ['nullable', 'string', 'max:60'],
            'channels.*.display' => ['nullable', 'string', 'max:60'],
        ], [
            'company_website.url' => 'نشانی سایت باید کامل باشد، مثلاً https://itmb.ir',
            'enamad_id.regex' => 'شناسه اینماد فقط عدد است.',
            'enamad_code.regex' => 'کد اینماد فقط حروف انگلیسی و عدد است.',
            'channels.*.value.required_with' => 'مقدار این راه تماس را وارد کنید.',
        ], [
            'company_logo' => 'لوگو',
            'enamad_id' => 'شناسه اینماد',
            'enamad_code' => 'کد اینماد',
        ]);

        $channels = [];
        $errors = [];
        foreach ($data['channels'] ?? [] as $index => $row) {
            if (blank($row['type'] ?? null)) {
                continue;
            }

            if ($error = ContactChannel::validationError($row['type'], trim($row['value']))) {
                $errors["channels.{$index}.value"] = $error;
                continue;
            }

            $channels[] = ContactChannel::fromArray($row)->toArray();
        }

        if ($errors) {
            return back()->withInput()->withErrors($errors);
        }

        $company = $settings->get('company');
        $logo = $company['logo'];

        if ($request->hasFile('company_logo')) {
            $logo = $request->file('company_logo')->store('site', 'uploads');
        } elseif ($request->boolean('remove_logo')) {
            $logo = null;
        }

        if ($company['logo'] && $company['logo'] !== $logo) {
            Storage::disk('uploads')->delete($company['logo']);
        }

        $user = $request->user();
        $settings->save('company', [
            'name' => $data['company_name'] ?? null,
            'website' => $data['company_website'] ?? null,
            'logo' => $logo,
            'intro' => $data['company_intro'] ?? null,
        ], $user);
        $settings->save('trust_seal', [
            'enamad_id' => $data['enamad_id'] ?? null,
            'enamad_code' => $data['enamad_code'] ?? null,
        ], $user);
        $settings->save('support', [
            'help_text' => $data['support_help_text'] ?? null,
            'channels' => $channels,
        ], $user);

        $audit->log($user, 'site_settings.updated', ['channels' => count($channels), 'enamad' => filled($data['enamad_id'] ?? null)]);

        return redirect()->route('admin.settings.site')->with('success', 'تنظیمات سایت ذخیره شد.');
    }
}

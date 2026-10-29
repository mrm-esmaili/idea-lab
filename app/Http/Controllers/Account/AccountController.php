<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Idea;
use Illuminate\Http\Request;

/**
 * حساب مخاطب. همه داده‌ها فقط از طریق روابط مخاطب واردشده خوانده
 * می‌شوند تا دسترسی به سفارش دیگران (IDOR) ممکن نباشد.
 */
class AccountController extends Controller
{
    public function dashboard(Request $request)
    {
        /** @var Contact $contact */
        $contact = $request->user('contact');

        $preorders = $contact->preorders()
            ->with(['landingPage.idea', 'refundPolicy'])
            ->latest()
            ->get();

        // علاقه اعلام‌شده: ایده‌هایی که مخاطب برایشان فرم علاقه‌مندی یا پیش‌خرید ثبت کرده است
        $ideaIds = $contact->leads()
            ->join('landing_pages', 'landing_pages.id', '=', 'leads.landing_page_id')
            ->pluck('landing_pages.idea_id')
            ->merge($preorders->pluck('landingPage.idea_id'))
            ->unique();

        $ideas = Idea::whereIn('id', $ideaIds)->orderBy('name')->get(['id', 'name', 'status']);

        return view('account.dashboard', compact('contact', 'preorders', 'ideas'));
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $request->user('contact')->update($data);

        return redirect()
            ->route('account.dashboard')
            ->with('success', 'اطلاعات شما ذخیره شد.');
    }

    public function updateConsent(Request $request)
    {
        $data = $request->validate([
            'marketing_sms_consent' => ['required', 'boolean'],
        ]);

        $request->user('contact')->setMarketingConsent(
            (bool) $data['marketing_sms_consent'],
            'account'
        );

        return redirect()
            ->route('account.dashboard')
            ->with('success', $data['marketing_sms_consent']
                ? 'دریافت پیامک‌های معرفی و پیشنهاد فعال شد.'
                : 'دریافت پیامک‌های معرفی و پیشنهاد لغو شد. پیام‌های مربوط به سفارش‌ها همچنان ارسال می‌شوند.');
    }
}

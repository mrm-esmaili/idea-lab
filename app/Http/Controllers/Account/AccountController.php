<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\Refunds\RefundException;
use App\Services\Refunds\RefundService;
use Illuminate\Http\Request;

/**
 * حساب مخاطب. همه داده‌ها فقط از طریق روابط مخاطب واردشده خوانده
 * می‌شوند تا دسترسی به سفارش دیگران (IDOR) ممکن نباشد.
 */
class AccountController extends Controller
{
    public function dashboard(Request $request, RefundService $refunds)
    {
        /** @var Contact $contact */
        $contact = $request->user('contact');

        $preorders = $contact->preorders()
            ->with(['landingPage.idea', 'refundPolicy', 'refundRequests' => fn ($q) => $q->latest('id')])
            ->latest()
            ->get()
            ->each(fn ($preorder) => $preorder->setAttribute('refund_eligibility', $refunds->customerEligibility($preorder)));

        // علاقه اعلام‌شده (فرم علاقه‌مندی، پیش‌خرید یا ثبت مدیر)
        $ideas = $contact->interests()->orderBy('name')->get(['ideas.id', 'ideas.name', 'ideas.status']);

        return view('account.dashboard', compact('contact', 'preorders', 'ideas'));
    }

    /**
     * درخواست بازپرداخت مشتری (مرحله ۵۰)؛ سفارش فقط از میان سفارش‌های خود مخاطب.
     */
    public function requestRefund(Request $request, int $preorder, RefundService $refunds)
    {
        $contact = $request->user('contact');
        $preorder = $contact->preorders()->with('refundPolicy')->findOrFail($preorder);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ], [], ['reason' => 'دلیل درخواست']);

        $eligibility = $refunds->customerEligibility($preorder);

        if (! $eligibility['eligible']) {
            return back()->withErrors(['refund_' . $preorder->id => $eligibility['reason'] ?? 'این سفارش قابل بازپرداخت نیست.']);
        }

        try {
            $refunds->request($preorder, $eligibility['payment'], $eligibility['max_rial'], $data['reason'], $contact);
        } catch (RefundException $e) {
            return back()->withErrors(['refund_' . $preorder->id => $e->getMessage()]);
        }

        return redirect()
            ->route('account.dashboard')
            ->with('success', 'درخواست بازپرداخت ثبت شد و پس از بررسی نتیجه اعلام می‌شود.');
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

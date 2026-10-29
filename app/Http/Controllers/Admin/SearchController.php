<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Contact;
use App\Models\Idea;
use App\Models\Lead;
use App\Models\Payment;
use App\Models\Preorder;
use App\Models\Refund;
use App\Models\RefundRequest;
use App\Support\Money;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;

/**
 * جست‌وجوی سریع سراسری (مرحله ۸۴)؛ هر بخش فقط با مجوز همان بخش جست‌وجو می‌شود.
 * شماره موبایل با رقم فارسی یا +98 هم پیدا می‌شود؛ «#عدد» شناسه سفارش یا درخواست است.
 */
class SearchController extends Controller
{
    private const LIMIT = 8;

    public function __invoke(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        // فقط رقم فارسی/عربی به لاتین؛ فاصله نام‌ها حفظ می‌شود
        $q = trim(strtr((string) ($data['q'] ?? ''), array_combine(
            ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
        )));
        $user = $request->user();
        $sections = [];

        if (mb_strlen($q) >= 2) {
            $mobile = PhoneNumber::normalizeMobile($q);
            $digits = preg_replace('/\D/', '', $q);
            $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';
            $phoneLike = '%' . ($mobile ?? ($digits !== '' && strlen($digits) >= 4 ? $digits : $q)) . '%';
            $id = preg_match('/^#?(\d+)$/', $q, $m) ? (int) $m[1] : null;

            if ($user->hasPermission('contacts.manage')) {
                $sections['contacts'] = ['مخاطبان', Contact::where('name', 'like', $like)->orWhere('mobile', 'like', $phoneLike)->limit(self::LIMIT)->get()
                    ->map(fn ($c) => [$c->name ?: 'بدون نام', $c->mobile, route('admin.contacts.show', $c)])];
            }

            if ($user->hasPermission('preorders.manage')) {
                $sections['preorders'] = ['پیش‌خریدها', Preorder::with('landingPage.idea')
                    ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $phoneLike)->when($id, fn ($w) => $w->orWhere('id', $id)))
                    ->latest('id')->limit(self::LIMIT)->get()
                    ->map(fn ($p) => ["#{$p->id} — {$p->name}", $p->phone . ' — ' . $p->landingPage?->idea?->name, route('admin.preorders.show', $p)])];
            }

            if ($user->hasPermission('leads.manage')) {
                $sections['leads'] = ['سرنخ‌ها', Lead::with('landingPage.idea')
                    ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('phone', 'like', $phoneLike))
                    ->latest('id')->limit(self::LIMIT)->get()
                    ->map(fn ($l) => [$l->name, $l->phone . ' — ' . $l->landingPage?->idea?->name, route('admin.leads.index', ['q' => $l->phone])])];
            }

            if ($user->hasPermission('finance.manage')) {
                $sections['payments'] = ['پرداخت‌ها (کد پیگیری یا شناسه درگاه)', Payment::with('preorder')
                    ->where(fn ($w) => $w->where('ref_id', $q)->orWhere('authority', $q))
                    ->limit(self::LIMIT)->get()
                    ->map(fn ($p) => ['کد پیگیری ' . ($p->ref_id ?? '—'), Money::formatRialAsToman($p->amount_rial) . ' — ' . $p->preorder?->name, route('admin.preorders.show', $p->preorder_id)])];

                $refundIds = Refund::where('reference', $q)->pluck('refund_request_id');
                $sections['refunds'] = ['بازپرداخت‌ها', RefundRequest::with('preorder')
                    ->where(fn ($w) => $w->whereIn('id', $refundIds)->when($id, fn ($w) => $w->orWhere('id', $id)))
                    ->limit(self::LIMIT)->get()
                    ->map(fn ($r) => ["درخواست #{$r->id}", $r->preorder?->name . ' — ' . config("statuses.refund_request.{$r->status}"), route('admin.refunds.show', $r)])];
            }

            if ($user->hasPermission('ideas.manage')) {
                $sections['ideas'] = ['ایده‌ها', Idea::where('name', 'like', $like)->orWhere('slug', 'like', $like)->limit(self::LIMIT)->get()
                    ->map(fn ($i) => [$i->name, config("statuses.idea.{$i->status}"), route('ideas.show', $i)])];
                $sections['campaigns'] = ['کمپین‌ها', Campaign::where('name', 'like', $like)->orWhere('slug', 'like', $like)->limit(self::LIMIT)->get()
                    ->map(fn ($c) => [$c->name, $c->slug, route('admin.campaigns.show', $c)])];
            }

            $sections = array_filter($sections, fn ($section) => $section[1]->isNotEmpty());
        }

        return view('admin.search', ['q' => $q, 'sections' => $sections]);
    }
}

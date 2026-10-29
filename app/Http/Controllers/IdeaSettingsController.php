<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Support\Money;
use Illuminate\Http\Request;

class IdeaSettingsController extends Controller
{
    /** حداکثر مبلغ قابل ورود (تومان) */
    private const MAX_TOMAN = 1_000_000_000;

    /** حداقل مبلغ پیش‌پرداخت (تومان)؛ زرین‌پال کمتر از ۱۰۰ تومان نمی‌پذیرد */
    private const MIN_PREORDER_TOMAN = 1_000;

    public function edit(Idea $idea)
    {
        $idea->load('currentRefundPolicy');

        return view('ideas.settings', compact('idea'));
    }

    public function update(Request $request, Idea $idea)
    {
        $request->merge([
            'budget_toman' => Money::normalizeInput($request->input('budget_toman')),
            'preorder_amount_toman' => Money::normalizeInput($request->input('preorder_amount_toman')),
            'lead_goal' => Money::normalizeInput($request->input('lead_goal')),
            'paid_preorder_goal' => Money::normalizeInput($request->input('paid_preorder_goal')),
        ]);

        $data = $request->validate([
            'budget_toman' => ['nullable', 'integer', 'min:0', 'max:' . self::MAX_TOMAN],
            'test_starts_on' => ['nullable', 'date'],
            'test_ends_on' => ['nullable', 'date', 'after_or_equal:test_starts_on'],
            'lead_goal' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'paid_preorder_goal' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'preorder_amount_toman' => [
                'nullable',
                'integer',
                'min:' . self::MIN_PREORDER_TOMAN,
                'max:' . self::MAX_TOMAN,
            ],
        ], [
            'preorder_amount_toman.min' => 'مبلغ پیش‌پرداخت حداقل ' . number_format(self::MIN_PREORDER_TOMAN) . ' تومان است.',
            'test_ends_on.after_or_equal' => 'پایان آزمایش نمی‌تواند قبل از شروع آن باشد.',
        ]);

        // تغییر مبلغ فقط روی سفارش‌های جدید اثر دارد؛ سفارش‌های قبلی مبلغ خود را دارند
        $idea->update([
            'budget_rial' => $this->toRial($data['budget_toman'] ?? null),
            'test_starts_on' => $data['test_starts_on'] ?? null,
            'test_ends_on' => $data['test_ends_on'] ?? null,
            'lead_goal' => $data['lead_goal'] ?? null,
            'paid_preorder_goal' => $data['paid_preorder_goal'] ?? null,
            'preorder_amount_rial' => $this->toRial($data['preorder_amount_toman'] ?? null),
        ]);

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', 'تنظیمات ایده ذخیره شد.');
    }

    private function toRial(mixed $toman): ?int
    {
        return $toman === null ? null : Money::tomanToRial((int) $toman);
    }
}

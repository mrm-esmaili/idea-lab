<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\RefundPolicy;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefundPolicyController extends Controller
{
    public function index(Idea $idea)
    {
        $policies = $idea->refundPolicies()
            ->with('creator')
            ->withCount('preorders')
            ->get();

        $current = $policies->first();

        return view('ideas.refund-policy', compact('idea', 'policies', 'current'));
    }

    public function store(Request $request, Idea $idea)
    {
        $data = $request->validate([
            'is_refundable' => ['required', 'boolean'],
            'refund_window_days' => [
                'nullable',
                'required_if:is_refundable,1',
                'integer',
                'min:1',
                'max:365',
            ],
            'eligibility' => ['nullable', 'string', 'max:2000'],
            'terms' => ['required', 'string', 'max:5000'],
        ], [
            'refund_window_days.required_if' => 'برای شرایط قابل بازپرداخت، مهلت درخواست را تعیین کنید.',
        ]);

        $isRefundable = (bool) $data['is_refundable'];

        $attributes = [
            'is_refundable' => $isRefundable,
            'refund_window_days' => $isRefundable ? (int) $data['refund_window_days'] : null,
            'eligibility' => $isRefundable ? ($data['eligibility'] ?? null) : null,
            'terms' => $data['terms'],
        ];

        $current = $idea->currentRefundPolicy;

        if ($current && $this->isSame($current, $attributes)) {
            return redirect()
                ->route('ideas.refund-policy', $idea)
                ->with('success', 'تغییری در شرایط ایجاد نشد.');
        }

        try {
            $policy = DB::transaction(function () use ($idea, $attributes, $request) {
                $nextVersion = (int) RefundPolicy::query()
                    ->where('idea_id', $idea->id)
                    ->lockForUpdate()
                    ->max('version') + 1;

                return $idea->refundPolicies()->create($attributes + [
                    'version' => $nextVersion,
                    'created_by' => $request->user()->id,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // دو ذخیره هم‌زمان؛ کلید یکتا (idea_id, version) نسخه تکراری را رد کرده است
            return back()
                ->withInput()
                ->withErrors(['terms' => 'هم‌زمان نسخه دیگری ثبت شد؛ دوباره بررسی و ذخیره کنید.']);
        }

        return redirect()
            ->route('ideas.refund-policy', $idea)
            ->with('success', "نسخه {$policy->version} شرایط بازپرداخت ثبت شد. سفارش‌های قبلی همان نسخه‌ای را دارند که پذیرفته‌اند.");
    }

    private function isSame(RefundPolicy $policy, array $attributes): bool
    {
        return $policy->is_refundable === $attributes['is_refundable']
            && $policy->refund_window_days === $attributes['refund_window_days']
            && (string) $policy->eligibility === (string) $attributes['eligibility']
            && $policy->terms === $attributes['terms'];
    }
}

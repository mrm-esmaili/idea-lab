<?php

namespace App\Services;

use App\Models\Preorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

/**
 * قواعد انتخاب مخاطبان؛ مشترک بین فیلتر فهرست مخاطبان، گروه‌های پویا
 * و خروجی. همه قواعد با «و» ترکیب می‌شوند.
 *
 * - ideas: علاقه‌مند به دست‌کم یکی از ایده‌ها
 * - campaign: جذب‌شده یا علاقه‌مندشده از طریق این کد کمپین
 * - payment: paid (دست‌کم یک پیش‌خرید پرداخت‌شده یا بخشی بازپرداخت‌شده)،
 *   refunded (فقط پیش‌خریدهای کامل بازپرداخت‌شده)، unpaid (پیش‌خرید دارد ولی
 *   هیچ‌کدام پرداخت نشده)، none (بدون پیش‌خرید)
 * - consent: yes/no — رضایت پیامک تبلیغاتی
 * - source: منبع اولین تماس (utm_source)
 * - lead_status: دست‌کم یک سرنخ با این وضعیت
 * - joined_within_days: ثبت‌شده در N روز اخیر
 * - q: جست‌وجوی نام یا موبایل (فقط فیلتر فهرست)
 */
class ContactSegment
{
    public const KEYS = ['q', 'ideas', 'campaign', 'payment', 'consent', 'source', 'lead_status', 'joined_within_days'];

    public static function validationRules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'ideas' => ['nullable', 'array', 'max:50'],
            'ideas.*' => ['integer', 'exists:ideas,id'],
            'campaign' => ['nullable', 'string', 'max:100'],
            'payment' => ['nullable', Rule::in(['paid', 'refunded', 'unpaid', 'none'])],
            'consent' => ['nullable', Rule::in(['yes', 'no'])],
            'source' => ['nullable', 'string', 'max:100'],
            'lead_status' => ['nullable', Rule::in(array_keys(config('statuses.lead')))],
            'joined_within_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ];
    }

    /**
     * فقط قواعد پرشده.
     */
    public static function clean(array $input): array
    {
        $rules = array_intersect_key($input, array_flip(self::KEYS));

        if (isset($rules['ideas'])) {
            $rules['ideas'] = array_values(array_map('intval', array_filter((array) $rules['ideas'])));
        }

        return array_filter($rules, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    public function apply(Builder $query, array $rules): Builder
    {
        $rules = self::clean($rules);

        if (isset($rules['q'])) {
            $term = '%' . $rules['q'] . '%';
            $query->where(fn ($q) => $q->where('contacts.name', 'like', $term)->orWhere('contacts.mobile', 'like', $term));
        }

        if (isset($rules['ideas'])) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('contact_idea_interests')
                ->whereColumn('contact_idea_interests.contact_id', 'contacts.id')
                ->whereIn('contact_idea_interests.idea_id', $rules['ideas']));
        }

        if (isset($rules['campaign'])) {
            $code = $rules['campaign'];
            $query->where(fn ($q) => $q->where('contacts.first_campaign', $code)
                ->orWhereExists(fn ($sub) => $sub->selectRaw('1')
                    ->from('contact_idea_interests')
                    ->whereColumn('contact_idea_interests.contact_id', 'contacts.id')
                    ->where('contact_idea_interests.campaign', $code)));
        }

        if (isset($rules['payment'])) {
            $hasPreorder = fn ($q) => $q->selectRaw('1')->from('preorders')->whereColumn('preorders.contact_id', 'contacts.id');
            $withStatus = fn (array $statuses) => fn ($q) => $hasPreorder($q)->whereIn('preorders.payment_status', $statuses);

            match ($rules['payment']) {
                // خریدار فعلی: پرداخت‌شده یا بخشی بازپرداخت‌شده
                'paid' => $query->whereExists($withStatus([Preorder::PAYMENT_PAID, Preorder::PAYMENT_PARTIALLY_REFUNDED])),
                'refunded' => $query->whereExists($withStatus([Preorder::PAYMENT_REFUNDED]))
                    ->whereNotExists($withStatus([Preorder::PAYMENT_PAID, Preorder::PAYMENT_PARTIALLY_REFUNDED])),
                'unpaid' => $query->whereExists($hasPreorder)->whereNotExists($withStatus(Preorder::PAID_STATUSES)),
                'none' => $query->whereNotExists($hasPreorder),
            };
        }

        if (isset($rules['consent'])) {
            $query->where('contacts.marketing_sms_consent', $rules['consent'] === 'yes');
        }

        if (isset($rules['source'])) {
            $query->where('contacts.first_source', $rules['source']);
        }

        if (isset($rules['lead_status'])) {
            $query->whereExists(fn ($q) => $q->selectRaw('1')
                ->from('leads')
                ->whereColumn('leads.contact_id', 'contacts.id')
                ->where('leads.status', $rules['lead_status']));
        }

        if (isset($rules['joined_within_days'])) {
            $query->where('contacts.created_at', '>=', now()->subDays((int) $rules['joined_within_days']));
        }

        return $query;
    }

    /**
     * توضیح فارسی قواعد برای نمایش.
     *
     * @return list<string>
     */
    public function describe(array $rules, array $ideaNames = []): array
    {
        $rules = self::clean($rules);
        $lines = [];

        if (isset($rules['q'])) {
            $lines[] = "جست‌وجو: «{$rules['q']}»";
        }

        if (isset($rules['ideas'])) {
            $names = array_map(fn ($id) => $ideaNames[$id] ?? "#{$id}", $rules['ideas']);
            $lines[] = 'علاقه‌مند به: ' . implode('، ', $names);
        }

        if (isset($rules['campaign'])) {
            $lines[] = "کمپین: {$rules['campaign']}";
        }

        if (isset($rules['payment'])) {
            $lines[] = 'پیش‌خرید: ' . ['paid' => 'پرداخت‌شده', 'refunded' => 'کامل بازپرداخت‌شده', 'unpaid' => 'پرداخت‌نشده', 'none' => 'ندارد'][$rules['payment']];
        }

        if (isset($rules['consent'])) {
            $lines[] = 'رضایت پیامک تبلیغاتی: ' . ($rules['consent'] === 'yes' ? 'دارد' : 'ندارد');
        }

        if (isset($rules['source'])) {
            $lines[] = "منبع جذب: {$rules['source']}";
        }

        if (isset($rules['lead_status'])) {
            $lines[] = 'وضعیت سرنخ: ' . config("statuses.lead.{$rules['lead_status']}");
        }

        if (isset($rules['joined_within_days'])) {
            $lines[] = "ثبت‌شده در {$rules['joined_within_days']} روز اخیر";
        }

        return $lines;
    }
}

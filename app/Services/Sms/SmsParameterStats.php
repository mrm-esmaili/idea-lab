<?php

namespace App\Services\Sms;

use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\SmsTemplate;
use App\Support\Money;
use App\Support\SmsText;
use Illuminate\Support\Facades\DB;

/**
 * طول واقعی مقدار هر متغیر پیامک از داده فعلی (بلندترین نام، ایده، مبلغ و ...)
 * تا مدیر پیش از ارسال ببیند کدام پارامتر از حد SMS.ir (۲۵ نویسه) بیشتر است.
 */
class SmsParameterStats
{
    /** @var array<string, array{max: int, sample: string, over: list<string>}>|null */
    private ?array $cache = null;

    public function limit(): int
    {
        return (int) config('sms.template_param_max', 25);
    }

    /**
     * @return array<string, array{max: int, sample: string, over: list<string>}>
     *         max: بیشترین طول؛ sample: نمونه همان مقدار؛ over: نمونه‌های بلندتر از حد (حداکثر ۱۰)
     */
    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $names = $this->longest([['contacts', 'name'], ['preorders', 'name'], ['leads', 'name']]);
        $ideas = Idea::pluck('name')->all();

        $maxRial = max(
            (int) DB::table('preorders')->max('amount_rial'),
            (int) DB::table('refund_requests')->max('requested_amount_rial'),
            (int) DB::table('refunds')->max('amount_rial'),
        );
        $amount = number_format(Money::rialToToman($maxRial));

        $pageLink = SmsText::shortUrl(route('short.page', (int) LandingPage::max('id') ?: 1));

        return $this->cache = [
            'name' => $this->summary($names),
            'idea' => $this->summary($ideas),
            'amount' => $this->summary([$amount]),
            'ref_id' => $this->summary($this->longest([['payments', 'ref_id']])),
            'reference' => $this->summary($this->longest([['refunds', 'reference']])),
            'link' => $this->summary([SmsText::shortUrl(route('short.account')), $pageLink]),
            'code' => $this->summary([str_repeat('9', (int) config('otp.length', 6))]),
        ];
    }

    /**
     * متغیرهای قالبی که در داده فعلی از حد بیشترند.
     *
     * @return list<string>
     */
    public function overLimit(SmsTemplate $template): array
    {
        $limit = $this->limit();
        $stats = $this->all();

        return array_values(array_filter(
            array_keys($template->parameters),
            fn ($variable) => filled($template->parameters[$variable]) && $limit > 0 && ($stats[$variable]['max'] ?? 0) > $limit
        ));
    }

    /**
     * بلندترین مقدارهای چند ستون (ستون‌ها ممکن است خالی باشند).
     *
     * @param  list<array{0: string, 1: string}>  $columns
     * @return list<string>
     */
    private function longest(array $columns): array
    {
        $length = in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) ? 'CHAR_LENGTH' : 'LENGTH';
        $values = [];

        foreach ($columns as [$table, $column]) {
            $values = array_merge($values, DB::table($table)
                ->whereNotNull($column)
                ->orderByRaw("{$length}({$column}) DESC")
                ->limit(10)
                ->pluck($column)
                ->map(fn ($value) => (string) $value)
                ->all());
        }

        return $values;
    }

    /**
     * @param  list<string>  $values
     */
    private function summary(array $values): array
    {
        $values = array_values(array_unique(array_map(fn ($value) => trim(preg_replace('/\s+/u', ' ', $value)), $values)));
        usort($values, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $limit = $this->limit();

        return [
            'max' => $values ? mb_strlen($values[0]) : 0,
            'sample' => $values[0] ?? '',
            'over' => $limit > 0 ? array_slice(array_values(array_filter($values, fn ($value) => mb_strlen($value) > $limit)), 0, 10) : [],
        ];
    }
}

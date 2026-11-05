<?php

namespace App\Services\Import;

use App\Enums\IdeaStatus;
use App\Models\Campaign;
use App\Models\Idea;
use App\Models\LandingPage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Jalali;
use App\Support\Money;
use App\Support\RichText;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * ورود گروهی ایده، صفحه فرود و کمپین از فایل Markdown/JSON (نسخه ۱.۲).
 *
 * فایل معمولاً با هوش مصنوعی و راهنمای resources/import/ai-prompt.md ساخته
 * می‌شود: یک یا چند بلوک ```json (هر بلوک یک ایده، فهرستی از ایده‌ها یا
 * {"ideas": [...]}). قواعد اعتبارسنجی همان قواعد فرم‌های پنل است. صفحه‌ها
 * منتشرنشده ساخته می‌شوند تا مدیر بازبینی کند و تصویر و ویدیو بگذارد.
 */
class IdeaImporter
{
    public const MAX_IDEAS = 50;

    public const MAX_CAMPAIGNS = 20;

    private const PAGE_SLUG = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private const CAMPAIGN_CODE = '/^[a-z0-9]+(?:[-_][a-z0-9]+)*$/';

    /** slugهای رزروشده در همین فایل (علاوه بر پایگاه داده) */
    private array $reserved = ['idea' => [], 'page' => [], 'campaign' => []];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * متن فایل ← فهرست ایده‌های خام.
     *
     * @return array{ideas: list<array>, errors: list<string>}
     */
    public function parse(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', str_replace("\r\n", "\n", $text));
        $trimmed = ltrim($text);

        // فایل JSON خالص، یا بلوک‌های ```json داخل Markdown
        $blocks = str_starts_with($trimmed, '{') || str_starts_with($trimmed, '[')
            ? [$trimmed]
            : (preg_match_all('/```(?:json)?[ \t]*\n(.*?)\n[ \t]*```/s', $text, $matches) ? $matches[1] : []);

        if ($blocks === []) {
            return ['ideas' => [], 'errors' => ['هیچ بلوک ```json در فایل پیدا نشد. فایل باید طبق راهنمای پرامپت ساخته شده باشد.']];
        }

        $ideas = [];
        $errors = [];

        foreach ($blocks as $index => $block) {
            $data = json_decode($this->repairJson($block), true);

            if (! is_array($data)) {
                $errors[] = 'بلوک JSON شماره ' . ($index + 1) . ' خوانا نیست (' . json_last_error_msg() . '). ممکن است خروجی هوش مصنوعی ناقص قطع شده باشد.';
                continue;
            }

            $items = match (true) {
                isset($data['ideas']) && is_array($data['ideas']) => $data['ideas'],
                array_is_list($data) => $data,
                default => [$data],
            };

            foreach ($items as $item) {
                if (is_array($item)) {
                    $ideas[] = $item;
                } else {
                    $errors[] = 'بلوک JSON شماره ' . ($index + 1) . ' شامل موردی است که ایده نیست.';
                }
            }
        }

        if (count($ideas) > self::MAX_IDEAS) {
            $errors[] = 'حداکثر ' . self::MAX_IDEAS . ' ایده در هر فایل؛ فایل را چند بخش کنید.';
            $ideas = array_slice($ideas, 0, self::MAX_IDEAS);
        }

        return ['ideas' => $ideas, 'errors' => $errors];
    }

    /**
     * بررسی بدون ساخت چیزی.
     *
     * @return list<array{index: int, name: string, errors: list<string>, warnings: list<string>, plan: ?array}>
     */
    public function preview(array $ideas): array
    {
        $this->reserved = ['idea' => [], 'page' => [], 'campaign' => []];

        return array_map(fn ($raw, $index) => $this->plan($raw, $index), $ideas, array_keys($ideas));
    }

    /**
     * ساخت همه ایده‌های بدون خطا در یک تراکنش (همه یا هیچ).
     *
     * @return list<Idea>
     */
    public function import(array $ideas, User $user, bool $skipInvalid = false): array
    {
        $plans = $this->preview($ideas);
        $invalid = array_filter($plans, fn ($plan) => $plan['errors'] !== []);

        if ($invalid !== [] && ! $skipInvalid) {
            throw new \RuntimeException('فایل خطا دارد؛ ابتدا خطاها را رفع کنید یا «رد کردن ایده‌های دارای خطا» را انتخاب کنید.');
        }

        return DB::transaction(function () use ($plans, $user) {
            $created = [];

            foreach ($plans as $plan) {
                if ($plan['errors'] !== []) {
                    continue;
                }

                $created[] = $this->create($plan['plan'], $user);
            }

            return $created;
        });
    }

    private function create(array $plan, User $user): Idea
    {
        $idea = Idea::create($plan['idea']);

        if ($plan['refund_policy']) {
            $idea->refundPolicies()->create($plan['refund_policy'] + ['version' => 1, 'created_by' => $user->id]);
        }

        $pages = [];
        foreach ($plan['pages'] as $key => $page) {
            $pages[$key] = $idea->landingPages()->create($page);
        }

        foreach ($plan['campaigns'] as $campaign) {
            $pageKey = $campaign['page'];
            unset($campaign['page']);
            Campaign::create($campaign + ['landing_page_id' => $pages[$pageKey]->id]);
        }

        $this->audit->log($idea, 'idea.imported', [
            'pages' => count($pages),
            'campaigns' => count($plan['campaigns']),
        ], $user);

        return $idea;
    }

    /**
     * @return array{index: int, name: string, errors: list<string>, warnings: list<string>, plan: ?array}
     */
    private function plan(array $raw, int $index): array
    {
        $name = trim((string) ($raw['name'] ?? ''));
        $errors = [];
        $warnings = [];

        $data = $this->normalize($raw);
        $validator = Validator::make($data, $this->rules(), $this->messages());

        foreach ($validator->errors()->messages() as $field => $messages) {
            $errors[] = $this->fieldLabel($field) . ': ' . $messages[0];
        }

        if ($errors !== []) {
            return ['index' => $index, 'name' => $name ?: 'ایده ' . ($index + 1), 'errors' => $errors, 'warnings' => [], 'plan' => null];
        }

        $slug = $this->uniqueSlug('idea', $data['slug'] ?? null, $name, $warnings, 'نامک ایده');

        // صفحه‌ها: «landing_page» یک صفحه، یا «landing_pages» چند صفحه
        $rawPages = $data['landing_pages'] ?? (isset($data['landing_page']) ? [$data['landing_page']] : []);
        $pages = [];
        $pageKeys = [];

        foreach ($rawPages as $pageIndex => $page) {
            [$attributes, $pageErrors, $pageWarnings] = $this->page($page, $name, $pageIndex);
            array_push($errors, ...$pageErrors);
            array_push($warnings, ...$pageWarnings);
            $pages[$pageIndex] = $attributes;
            $pageKeys[strtolower((string) ($page['slug'] ?? ''))] = $pageIndex;
            $pageKeys[$attributes['slug']] = $pageIndex;
        }

        $campaigns = [];
        foreach ($data['campaigns'] ?? [] as $campaignIndex => $campaign) {
            if ($pages === []) {
                $errors[] = 'کمپین‌ها: کمپین بدون صفحه فرود ممکن نیست؛ «landing_page» را تعریف کنید.';
                break;
            }

            $reference = strtolower(trim((string) ($campaign['landing_page'] ?? '')));
            if ($reference !== '' && ! isset($pageKeys[$reference])) {
                $errors[] = 'کمپین ' . ($campaignIndex + 1) . ": صفحه «{$reference}» در این ایده تعریف نشده است.";
                continue;
            }

            $code = $this->uniqueSlug('campaign', $campaign['code'] ?? null, $slug . '-' . ($campaign['source'] ?? ($campaignIndex + 1)), $warnings, 'کد کمپین ' . ($campaignIndex + 1));

            $campaigns[] = [
                'page' => $reference !== '' ? $pageKeys[$reference] : array_key_first($pages),
                'name' => $campaign['name'],
                'slug' => $code,
                'source' => $campaign['source'] ?? null,
                'medium' => $campaign['medium'] ?? null,
                'status' => $campaign['status'] ?? 'planned',
                'starts_on' => $campaign['starts_on'] ?? null,
                'ends_on' => $campaign['ends_on'] ?? null,
                'budget_rial' => isset($campaign['budget_toman']) ? Money::tomanToRial((int) $campaign['budget_toman']) : null,
                'notes' => $campaign['notes'] ?? null,
            ];
        }

        if ($errors !== []) {
            return ['index' => $index, 'name' => $name, 'errors' => $errors, 'warnings' => $warnings, 'plan' => null];
        }

        $policy = null;
        if (isset($data['refund_policy'])) {
            $refundable = (bool) $data['refund_policy']['is_refundable'];
            $policy = [
                'is_refundable' => $refundable,
                'refund_window_days' => $refundable ? (int) $data['refund_policy']['refund_window_days'] : null,
                'eligibility' => $refundable ? ($data['refund_policy']['eligibility'] ?? null) : null,
                'terms' => $data['refund_policy']['terms'],
            ];
        } elseif ($pages !== []) {
            $warnings[] = 'شرایط بازپرداخت ندارد؛ تا ثبت آن در پنل، فرم پیش‌خرید نمایش داده نمی‌شود.';
        }

        if (! isset($data['preorder_amount_toman']) && $pages !== []) {
            $warnings[] = 'مبلغ پیش‌پرداخت ندارد؛ تا تعیین آن در تنظیمات ایده، فرم پیش‌خرید نمایش داده نمی‌شود.';
        }

        return [
            'index' => $index,
            'name' => $name,
            'errors' => [],
            'warnings' => $warnings,
            'plan' => [
                'idea' => [
                    'name' => $name,
                    'slug' => $slug,
                    'description' => $data['description'] ?? null,
                    'status' => $data['status'] ?? IdeaStatus::Draft->value,
                    'budget_rial' => isset($data['budget_toman']) ? Money::tomanToRial((int) $data['budget_toman']) : null,
                    'preorder_amount_rial' => isset($data['preorder_amount_toman']) ? Money::tomanToRial((int) $data['preorder_amount_toman']) : null,
                    'test_starts_on' => $data['test_starts_on'] ?? null,
                    'test_ends_on' => $data['test_ends_on'] ?? null,
                    'lead_goal' => $data['lead_goal'] ?? null,
                    'paid_preorder_goal' => $data['paid_preorder_goal'] ?? null,
                ],
                'refund_policy' => $policy,
                'pages' => $pages,
                'campaigns' => $campaigns,
            ],
        ];
    }

    /**
     * @return array{0: array, 1: list<string>, 2: list<string>}
     */
    private function page(array $page, string $ideaName, int $pageIndex): array
    {
        $errors = [];
        $warnings = [];
        $label = 'صفحه فرود' . ($pageIndex > 0 ? ' ' . ($pageIndex + 1) : '');

        $rich = function (?string $value, string $field, int $max) use (&$errors, $label) {
            $value = (string) $value;
            $html = str_contains($value, '<') ? RichText::sanitize($value) : RichText::fromPlain($value);

            if ($html !== null && mb_strlen(RichText::plain($html)) > $max) {
                $errors[] = "{$label} ← {$field}: متن حداکثر " . number_format($max) . ' نویسه باشد.';
            }

            return $html;
        };

        $aparat = null;
        if (filled($page['video_aparat'] ?? null)) {
            $aparat = LandingPage::aparatHash((string) $page['video_aparat']);
            if ($aparat === null) {
                $warnings[] = "{$label}: لینک آپارات معتبر نبود و نادیده گرفته شد.";
            }
        }

        $preorder = $page['preorder'] ?? [];

        $content = [
            'body_title' => $page['body_title'] ?? null,
            'body_text' => $rich($page['body'] ?? null, 'body', 5000),
            'features' => array_values(array_map(fn ($f) => [
                'title' => trim($f['title']),
                'description' => filled($f['description'] ?? null) ? trim($f['description']) : null,
            ], $page['features'] ?? [])),
            'faq' => array_values(array_filter(array_map(fn ($item) => [
                'q' => trim($item['q']),
                'a' => (string) $rich($item['a'], 'faq', 2000),
            ], $page['faq'] ?? []), fn ($item) => $item['a'] !== '')),
            'hero_image' => null,
            'show_lead_form' => $page['show_lead_form'] ?? true,
            'show_preorder_form' => $page['show_preorder_form'] ?? true,
            'demo_url' => $page['demo_url'] ?? null,
            'demo_label' => $page['demo_label'] ?? null,
            'demo_new_tab' => true,
            'video_aparat' => $aparat,
            'video_file' => null,
            'preorder_title' => $preorder['title'] ?? null,
            'preorder_intro' => $rich($preorder['intro'] ?? null, 'preorder.intro', 1000),
            'preorder_badge' => $preorder['badge'] ?? null,
            'preorder_original_price_toman' => isset($preorder['original_price_toman']) ? (int) $preorder['original_price_toman'] : null,
            'preorder_includes' => array_values(array_map('trim', $preorder['includes'] ?? [])),
            'preorder_guarantee' => $rich($preorder['guarantee'] ?? null, 'preorder.guarantee', 1000),
            'preorder_button' => $preorder['button'] ?? null,
            'support_text' => $page['support_text'] ?? null,
            'support_channels' => [],
            'show_trust' => $page['show_trust'] ?? true,
            'rich_text' => true,
        ];

        $slug = $this->uniqueSlug('page', $page['slug'] ?? null, $page['title'] ?? $ideaName, $warnings, "نامک {$label}");

        return [[
            'name' => $page['name'] ?? ('صفحه ' . $ideaName),
            'slug' => $slug,
            'title' => $page['title'],
            'description' => $page['description'] ?? null,
            'cta_text' => $page['cta_text'] ?? null,
            'content' => $content,
            // منتشرنشده تا بازبینی، تصویر و ویدیو
            'is_active' => (bool) ($page['publish'] ?? false),
        ], $errors, $warnings];
    }

    private function rules(): array
    {
        $toman = ['nullable', 'integer', 'min:0', 'max:1000000000'];
        $date = ['nullable', 'date_format:Y-m-d'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['nullable', Rule::in([IdeaStatus::Draft->value, IdeaStatus::Testing->value])],
            'budget_toman' => $toman,
            'preorder_amount_toman' => ['nullable', 'integer', 'min:1000', 'max:1000000000'],
            'test_starts_on' => $date,
            'test_ends_on' => [...$date, 'after_or_equal:test_starts_on'],
            'lead_goal' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'paid_preorder_goal' => ['nullable', 'integer', 'min:1', 'max:10000000'],

            'refund_policy' => ['nullable', 'array'],
            'refund_policy.is_refundable' => ['required_with:refund_policy', 'boolean'],
            'refund_policy.refund_window_days' => ['nullable', 'required_if:refund_policy.is_refundable,true', 'integer', 'min:1', 'max:365'],
            'refund_policy.eligibility' => ['nullable', 'string', 'max:2000'],
            'refund_policy.terms' => ['required_with:refund_policy', 'string', 'max:5000'],

            'landing_page' => ['nullable', 'array'],
            'landing_pages' => ['nullable', 'array', 'max:5'],
            ...$this->pageRules('landing_page'),
            ...$this->pageRules('landing_pages.*'),

            'campaigns' => ['nullable', 'array', 'max:' . self::MAX_CAMPAIGNS],
            'campaigns.*.name' => ['required', 'string', 'max:255'],
            'campaigns.*.code' => ['nullable', 'string', 'max:100', 'regex:' . self::CAMPAIGN_CODE],
            'campaigns.*.source' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'campaigns.*.medium' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9._-]+$/'],
            'campaigns.*.status' => ['nullable', Rule::in(Campaign::STATUSES)],
            'campaigns.*.starts_on' => $date,
            'campaigns.*.ends_on' => [...$date, 'after_or_equal:campaigns.*.starts_on'],
            'campaigns.*.budget_toman' => $toman,
            'campaigns.*.notes' => ['nullable', 'string', 'max:2000'],
            'campaigns.*.landing_page' => ['nullable', 'string', 'max:80'],
        ];
    }

    private function pageRules(string $prefix): array
    {
        return [
            "{$prefix}.name" => ['nullable', 'string', 'max:255'],
            "{$prefix}.slug" => ['nullable', 'string', 'max:80'],
            "{$prefix}.title" => ['required_with:' . rtrim($prefix, '.*'), 'string', 'max:255'],
            "{$prefix}.description" => ['nullable', 'string', 'max:5000'],
            "{$prefix}.cta_text" => ['nullable', 'string', 'max:255'],
            "{$prefix}.body_title" => ['nullable', 'string', 'max:255'],
            "{$prefix}.body" => ['nullable', 'string', 'max:30000'],
            "{$prefix}.features" => ['nullable', 'array', 'max:12'],
            "{$prefix}.features.*.title" => ['required', 'string', 'max:120'],
            "{$prefix}.features.*.description" => ['nullable', 'string', 'max:500'],
            "{$prefix}.faq" => ['nullable', 'array', 'max:8'],
            "{$prefix}.faq.*.q" => ['required', 'string', 'max:255'],
            "{$prefix}.faq.*.a" => ['required', 'string', 'max:10000'],
            "{$prefix}.demo_url" => ['nullable', 'url:https,http', 'max:500'],
            "{$prefix}.demo_label" => ['nullable', 'string', 'max:60'],
            "{$prefix}.video_aparat" => ['nullable', 'string', 'max:300'],
            "{$prefix}.preorder" => ['nullable', 'array'],
            "{$prefix}.preorder.title" => ['nullable', 'string', 'max:120'],
            "{$prefix}.preorder.intro" => ['nullable', 'string', 'max:6000'],
            "{$prefix}.preorder.badge" => ['nullable', 'string', 'max:60'],
            "{$prefix}.preorder.original_price_toman" => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            "{$prefix}.preorder.includes" => ['nullable', 'array', 'max:8'],
            "{$prefix}.preorder.includes.*" => ['string', 'max:200'],
            "{$prefix}.preorder.guarantee" => ['nullable', 'string', 'max:6000'],
            "{$prefix}.preorder.button" => ['nullable', 'string', 'max:60'],
            "{$prefix}.support_text" => ['nullable', 'string', 'max:300'],
            "{$prefix}.show_lead_form" => ['nullable', 'boolean'],
            "{$prefix}.show_preorder_form" => ['nullable', 'boolean'],
            "{$prefix}.show_trust" => ['nullable', 'boolean'],
            "{$prefix}.publish" => ['nullable', 'boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'campaigns.*.code.regex' => 'کد کمپین فقط حروف کوچک انگلیسی، عدد، خط تیره و زیرخط باشد.',
            'campaigns.*.source.regex' => 'منبع فقط حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط باشد.',
            'campaigns.*.medium.regex' => 'رسانه فقط حروف انگلیسی، عدد، نقطه، خط تیره و زیرخط باشد.',
            'date_format' => 'تاریخ باید به شکل 2026-11-20 یا 1405/08/29 باشد.',
            'preorder_amount_toman.min' => 'مبلغ پیش‌پرداخت حداقل ۱٬۰۰۰ تومان است.',
        ];
    }

    /**
     * یکسان‌سازی پیش از اعتبارسنجی: اعداد فارسی و جداکننده، تاریخ شمسی، بولی متنی.
     */
    private function normalize(array $raw): array
    {
        $number = fn ($value) => is_string($value) ? Money::normalizeInput($value) : $value;

        foreach (['budget_toman', 'preorder_amount_toman', 'lead_goal', 'paid_preorder_goal'] as $key) {
            if (array_key_exists($key, $raw)) {
                $raw[$key] = $number($raw[$key]);
            }
        }

        foreach (['test_starts_on', 'test_ends_on'] as $key) {
            if (array_key_exists($key, $raw)) {
                $raw[$key] = $this->date($raw[$key]);
            }
        }

        if (isset($raw['refund_policy']['refund_window_days'])) {
            $raw['refund_policy']['refund_window_days'] = $number($raw['refund_policy']['refund_window_days']);
        }

        foreach (['landing_page', 'landing_pages'] as $key) {
            if (! isset($raw[$key]) || ! is_array($raw[$key])) {
                continue;
            }

            $normalizePage = function (array $page) use ($number) {
                if (isset($page['preorder']['original_price_toman'])) {
                    $page['preorder']['original_price_toman'] = $number($page['preorder']['original_price_toman']);
                }
                // عنوان ویژگی به‌تنهایی (رشته) هم پذیرفته می‌شود
                if (isset($page['features']) && is_array($page['features'])) {
                    $page['features'] = array_map(fn ($f) => is_string($f) ? ['title' => $f] : $f, $page['features']);
                }
                $page['slug'] = isset($page['slug']) && is_string($page['slug']) ? strtolower(trim($page['slug'])) : ($page['slug'] ?? null);

                return $page;
            };

            $raw[$key] = $key === 'landing_pages' ? array_map(fn ($p) => is_array($p) ? $normalizePage($p) : $p, $raw[$key]) : $normalizePage($raw[$key]);
        }

        if (isset($raw['campaigns']) && is_array($raw['campaigns'])) {
            $raw['campaigns'] = array_map(function ($campaign) use ($number) {
                if (! is_array($campaign)) {
                    return $campaign;
                }
                foreach (['starts_on', 'ends_on'] as $key) {
                    if (array_key_exists($key, $campaign)) {
                        $campaign[$key] = $this->date($campaign[$key]);
                    }
                }
                if (array_key_exists('budget_toman', $campaign)) {
                    $campaign['budget_toman'] = $number($campaign['budget_toman']);
                }
                foreach (['code', 'source', 'medium', 'status'] as $key) {
                    if (isset($campaign[$key]) && is_string($campaign[$key])) {
                        $campaign[$key] = $key === 'code' ? strtolower(trim($campaign[$key])) : trim($campaign[$key]);
                    }
                }

                return $campaign;
            }, $raw['campaigns']);
        }

        return $raw;
    }

    /**
     * «2026-11-20»، «1405/08/29» یا «۱۴۰۵-۰۸-۲۹» ← تاریخ میلادی Y-m-d.
     */
    private function date(mixed $value): mixed
    {
        if (! is_string($value) || trim($value) === '') {
            return $value === '' ? null : $value;
        }

        $value = Money::normalizeInput(str_replace(['/', '.'], '-', $value)) ?? '';
        $value = str_replace(['/', '.'], '-', (string) $value);

        if (! preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $m)) {
            return $value;
        }

        [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];

        if ($y < 1700) {
            if ($mo < 1 || $mo > 12 || $d < 1 || $d > 31) {
                return $value;
            }
            [$y, $mo, $d] = Jalali::toGregorian($y, $mo, $d);
        }

        return sprintf('%04d-%02d-%02d', $y, $mo, $d);
    }

    /**
     * slug درخواستی اگر معتبر و آزاد باشد، وگرنه slug ساخته‌شده (با هشدار).
     */
    private function uniqueSlug(string $type, ?string $requested, string $fallbackSource, array &$warnings, string $label): string
    {
        [$model, $pattern, $default] = match ($type) {
            'idea' => [Idea::class, self::PAGE_SLUG, 'idea'],
            'page' => [LandingPage::class, self::PAGE_SLUG, 'page'],
            'campaign' => [Campaign::class, self::CAMPAIGN_CODE, 'campaign'],
        };

        $requested = $requested !== null ? strtolower(trim($requested)) : null;
        $taken = fn (string $slug) => in_array($slug, $this->reserved[$type], true) || $model::where('slug', $slug)->exists();

        if ($requested !== null && $requested !== '') {
            if (preg_match($pattern, $requested) && ! $taken($requested)) {
                return $this->reserved[$type][] = $requested;
            }

            $base = $this->asciiSlug($requested) ?: $this->asciiSlug($fallbackSource) ?: $default;
        } else {
            $base = $this->asciiSlug($fallbackSource) ?: $default;
        }

        $slug = $base;
        while ($taken($slug)) {
            $slug = Str::limit($base, 70, '') . '-' . Str::lower(Str::random(4));
        }

        if ($requested !== null && $requested !== '' && $slug !== $requested) {
            $warnings[] = "{$label} «{$requested}» " . (preg_match($pattern, $requested) ? 'تکراری بود' : 'نامعتبر بود') . " و «{$slug}» استفاده شد.";
        }

        return $this->reserved[$type][] = $slug;
    }

    private function asciiSlug(string $value): string
    {
        return Str::limit(trim(Str::slug($value), '-'), 70, '');
    }

    /**
     * خروجی رایج هوش مصنوعی گاهی ویرگول اضافه پیش از } یا ] دارد.
     */
    private function repairJson(string $json): string
    {
        return preg_replace('/,(\s*[}\]])/', '$1', trim($json));
    }

    /**
     * «campaigns.2.code» ← «کمپین ۳ ← code»
     */
    private function fieldLabel(string $field): string
    {
        $names = [
            'name' => 'نام', 'slug' => 'نامک', 'description' => 'توضیح', 'status' => 'وضعیت',
            'budget_toman' => 'بودجه (تومان)', 'preorder_amount_toman' => 'مبلغ پیش‌پرداخت (تومان)',
            'test_starts_on' => 'شروع آزمون', 'test_ends_on' => 'پایان آزمون',
            'lead_goal' => 'هدف سرنخ', 'paid_preorder_goal' => 'هدف پیش‌پرداخت',
            'refund_policy' => 'شرایط بازپرداخت', 'landing_page' => 'صفحه فرود', 'landing_pages' => 'صفحه فرود',
            'campaigns' => 'کمپین', 'features' => 'ویژگی', 'faq' => 'پرسش', 'preorder' => 'پیش‌خرید',
            'includes' => 'چه می‌گیری',
            'code' => 'کد', 'source' => 'منبع', 'medium' => 'رسانه', 'title' => 'عنوان', 'q' => 'پرسش', 'a' => 'پاسخ',
            'terms' => 'متن شرایط', 'refund_window_days' => 'مهلت (روز)', 'is_refundable' => 'قابل بازپرداخت',
            'starts_on' => 'شروع', 'ends_on' => 'پایان', 'intro' => 'معرفی', 'guarantee' => 'ضمانت', 'body' => 'متن معرفی',
        ];

        $parts = [];
        foreach (explode('.', $field) as $segment) {
            if (ctype_digit($segment) && $parts !== []) {
                $parts[count($parts) - 1] .= ' ' . ((int) $segment + 1);
            } else {
                $parts[] = $names[$segment] ?? $segment;
            }
        }

        return implode(' ← ', $parts);
    }
}

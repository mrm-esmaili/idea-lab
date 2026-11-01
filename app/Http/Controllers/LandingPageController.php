<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\LandingPage;
use App\Support\ContactChannel;
use App\Support\Money;
use App\Support\RichText;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LandingPageController extends Controller
{
    private const MAX_FEATURES = 12;

    private const MAX_FAQ = 8;

    private const MAX_INCLUDES = 8;

    private const MAX_SUPPORT_CHANNELS = 4;

    public function create(Idea $idea)
    {
        $landingPage = new LandingPage([
            'content' => LandingPage::CONTENT_DEFAULTS,
        ]);

        return view('landing-pages.create', compact('idea', 'landingPage'));
    }

    public function store(Request $request, Idea $idea)
    {
        $data = $request->validate($this->rules());

        $slug = filled($data['slug'] ?? null)
            ? $data['slug']
            : Slug::unique(LandingPage::class, $data['name'], 'page');

        $content = $this->buildContent($request, $data, LandingPage::CONTENT_DEFAULTS);

        $landingPage = $idea->landingPages()->create([
            'name' => $data['name'],
            'slug' => $slug,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'cta_text' => $data['cta_text'] ?? null,
            'content' => $content,
            // بدون انتشار: پیش‌نمایش در پنل، سپس انتشار از صفحه ایده
            'is_active' => $request->boolean('publish', true),
        ]);

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', $landingPage->is_active
                ? "صفحه فرود «{$landingPage->name}» ساخته و منتشر شد."
                : "صفحه فرود «{$landingPage->name}» ساخته شد؛ پس از پیش‌نمایش آن را منتشر کنید.");
    }

    public function edit(LandingPage $landingPage)
    {
        $landingPage->load('idea');
        $idea = $landingPage->idea;

        return view('landing-pages.edit', compact('idea', 'landingPage'));
    }

    public function update(Request $request, LandingPage $landingPage)
    {
        $data = $request->validate($this->rules($landingPage));

        $content = $this->buildContent(
            $request,
            $data,
            $landingPage->contentData()
        );

        $landingPage->update([
            'name' => $data['name'],
            'slug' => filled($data['slug'] ?? null)
                ? $data['slug']
                : $landingPage->slug,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'cta_text' => $data['cta_text'] ?? null,
            'content' => $content,
        ]);

        return redirect()
            ->route('ideas.show', $landingPage->idea_id)
            ->with('success', "صفحه فرود «{$landingPage->name}» به‌روزرسانی شد.");
    }

    /**
     * پیش‌نمایش برای مدیر (حتی صفحه منتشرنشده)، بدون ثبت بازدید.
     */
    public function preview(LandingPage $landingPage)
    {
        $landingPage->load('idea.currentRefundPolicy');

        return response()
            ->view('landing-pages.show', ['landingPage' => $landingPage, 'preview' => true])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function toggle(LandingPage $landingPage)
    {
        $landingPage->update([
            'is_active' => ! $landingPage->is_active,
        ]);

        $message = $landingPage->is_active
            ? "صفحه فرود «{$landingPage->name}» منتشر شد."
            : "صفحه فرود «{$landingPage->name}» غیرفعال شد و دیگر عمومی نیست.";

        return redirect()
            ->route('ideas.show', $landingPage->idea_id)
            ->with('success', $message);
    }

    private function rules(?LandingPage $landingPage = null): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:80',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('landing_pages', 'slug')
                    ->ignore($landingPage?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'cta_text' => ['nullable', 'string', 'max:255'],

            'body_title' => ['nullable', 'string', 'max:255'],
            // HTML ویرایشگر؛ طول متن خالص جدا بررسی می‌شود
            'body_text' => ['nullable', 'string', 'max:30000'],
            // آرایه عنوان/توضیح، یا متن هر خط یک ویژگی (فرم نسخه ۱.۰)
            'features' => ['nullable', is_array($this->featuresInput()) ? 'array' : 'string', is_array($this->featuresInput()) ? 'max:' . (self::MAX_FEATURES + 4) : 'max:3000'],
            'features.*.title' => ['nullable', 'string', 'max:120'],
            'features.*.description' => ['nullable', 'string', 'max:500'],
            'faq' => ['nullable', 'array', 'max:' . self::MAX_FAQ],
            'faq.*.q' => ['nullable', 'string', 'max:255'],
            'faq.*.a' => ['nullable', 'string', 'max:10000', 'required_with:faq.*.q'],
            'hero_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:2048',
                'dimensions:max_width=4000,max_height=4000',
            ],
            'remove_hero_image' => ['nullable', 'boolean'],
            'show_lead_form' => ['nullable', 'boolean'],
            'show_preorder_form' => ['nullable', 'boolean'],
            'publish' => ['nullable', 'boolean'],

            'demo_url' => ['nullable', 'url:https,http', 'max:500'],
            'demo_label' => ['nullable', 'string', 'max:60'],
            'demo_new_tab' => ['nullable', 'boolean'],
            'video_aparat' => ['nullable', 'string', 'max:300'],
            'video_file' => ['nullable', 'file', 'mimetypes:video/mp4', 'max:' . (config('landing.video_max_mb') * 1024)],
            'remove_video_file' => ['nullable', 'boolean'],

            'preorder_title' => ['nullable', 'string', 'max:120'],
            'preorder_intro' => ['nullable', 'string', 'max:6000'],
            'preorder_badge' => ['nullable', 'string', 'max:60'],
            'preorder_original_price_toman' => ['nullable', 'string', 'max:20'],
            'preorder_includes' => ['nullable', 'string', 'max:2000'],
            'preorder_guarantee' => ['nullable', 'string', 'max:6000'],
            'preorder_button' => ['nullable', 'string', 'max:60'],

            'support_text' => ['nullable', 'string', 'max:300'],
            'support_channels' => ['nullable', 'array', 'max:' . self::MAX_SUPPORT_CHANNELS],
            'support_channels.*.type' => ['nullable', Rule::in(array_keys(ContactChannel::TYPES))],
            'support_channels.*.value' => ['nullable', 'string', 'max:120', 'required_with:support_channels.*.type'],
            'support_channels.*.label' => ['nullable', 'string', 'max:60'],
            'show_trust' => ['nullable', 'boolean'],
        ];
    }

    private function buildContent(
        Request $request,
        array $data,
        array $current
    ): array {
        $features = $this->features($data['features'] ?? null);

        $faq = collect($data['faq'] ?? [])
            ->map(fn ($item) => [
                'q' => trim((string) ($item['q'] ?? '')),
                'a' => (string) $this->richText($item['a'] ?? null, 'faq', 2000),
            ])
            ->filter(fn ($item) => $item['q'] !== '' && $item['a'] !== '')
            ->values()
            ->all();

        $oldImage = $current['hero_image'] ?? null;
        $heroImage = $oldImage;

        // فایل جدید اول ذخیره می‌شود تا شکست آپلود تصویر قبلی را از بین نبرد
        if ($request->hasFile('hero_image')) {
            $heroImage = $request->file('hero_image')
                ->store('landing-pages', 'uploads');
        } elseif ($request->boolean('remove_hero_image')) {
            $heroImage = null;
        }

        if ($oldImage && $oldImage !== $heroImage) {
            Storage::disk('uploads')->delete($oldImage);
        }

        $video = $this->videoFile($request, $current['video_file'] ?? null);
        $includes = $this->lines($data['preorder_includes'] ?? null);

        if (count($includes) > self::MAX_INCLUDES) {
            throw ValidationException::withMessages(['preorder_includes' => 'حداکثر ' . self::MAX_INCLUDES . ' مورد مجاز است.']);
        }

        $aparat = null;
        if (filled($data['video_aparat'] ?? null)) {
            $aparat = LandingPage::aparatHash($data['video_aparat'])
                ?? throw ValidationException::withMessages(['video_aparat' => 'نشانی ویدیوی آپارات معتبر نیست (مثلاً https://www.aparat.com/v/abc123).']);
        }

        $originalPrice = null;
        if (filled($data['preorder_original_price_toman'] ?? null)) {
            $normalized = Money::normalizeInput($data['preorder_original_price_toman']);
            $originalPrice = is_string($normalized) && ctype_digit($normalized) && strlen($normalized) <= 12 ? (int) $normalized : null;

            if ($originalPrice === null || $originalPrice <= 0) {
                throw ValidationException::withMessages(['preorder_original_price_toman' => 'قیمت اصلی را به تومان و فقط با عدد وارد کنید.']);
            }
        }

        return [
            'body_title' => $data['body_title'] ?? null,
            'body_text' => $this->richText($data['body_text'] ?? null, 'body_text', 5000),
            'features' => $features,
            'faq' => $faq,
            'hero_image' => $heroImage,
            'show_lead_form' => $request->boolean('show_lead_form'),
            'show_preorder_form' => $request->boolean('show_preorder_form'),
            'demo_url' => $data['demo_url'] ?? null,
            'demo_label' => $data['demo_label'] ?? null,
            'demo_new_tab' => $request->boolean('demo_new_tab', true),
            'video_aparat' => $aparat,
            'video_file' => $video,
            'preorder_title' => $data['preorder_title'] ?? null,
            'preorder_intro' => $this->richText($data['preorder_intro'] ?? null, 'preorder_intro', 1000),
            'preorder_badge' => $data['preorder_badge'] ?? null,
            'preorder_original_price_toman' => $originalPrice,
            'preorder_includes' => $includes,
            'preorder_guarantee' => $this->richText($data['preorder_guarantee'] ?? null, 'preorder_guarantee', 1000),
            'preorder_button' => $data['preorder_button'] ?? null,
            'support_text' => $data['support_text'] ?? null,
            'support_channels' => $this->supportChannels($data['support_channels'] ?? []),
            'show_trust' => $request->boolean('show_trust', true),
            'rich_text' => true,
        ];
    }

    /**
     * HTML ویرایشگر ← HTML امن. ورودی بدون تگ (مرورگر بدون جاوااسکریپت یا
     * درخواست قدیمی) متن ساده است و خط‌هایش حفظ می‌شود.
     */
    private function richText(?string $input, string $field, int $maxPlainLength): ?string
    {
        $input = (string) $input;
        $html = str_contains($input, '<') ? RichText::sanitize($input) : RichText::fromPlain($input);

        if ($html !== null && mb_strlen(RichText::plain($html)) > $maxPlainLength) {
            throw ValidationException::withMessages([$field => 'متن حداکثر ' . number_format($maxPlainLength) . ' نویسه باشد.']);
        }

        return $html;
    }

    private function featuresInput(): mixed
    {
        return request()->input('features');
    }

    /**
     * @return list<array{title: string, description: ?string}>
     */
    private function features(mixed $input): array
    {
        $features = is_array($input)
            ? collect($input)->map(fn ($row) => [
                'title' => trim((string) ($row['title'] ?? '')),
                'description' => trim((string) ($row['description'] ?? '')) ?: null,
            ])
            : collect($this->lines($input))->map(fn ($line) => ['title' => $line, 'description' => null]);

        if ($features->contains(fn ($feature) => $feature['title'] === '' && $feature['description'] !== null)) {
            throw ValidationException::withMessages(['features' => 'هر ویژگی که توضیح دارد، عنوان هم لازم دارد.']);
        }

        $features = $features->filter(fn ($feature) => $feature['title'] !== '')->values();

        if ($features->count() > self::MAX_FEATURES) {
            throw ValidationException::withMessages([
                'features' => 'حداکثر ' . self::MAX_FEATURES . ' ویژگی مجاز است.',
            ]);
        }

        if ($features->contains(fn ($feature) => mb_strlen($feature['title']) > 200)) {
            throw ValidationException::withMessages([
                'features' => 'هر ویژگی حداکثر ۲۰۰ کاراکتر باشد.',
            ]);
        }

        return $features->all();
    }

    /**
     * @return list<string>
     */
    private function lines(?string $text): array
    {
        return collect(preg_split('/\R/u', (string) $text))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    private function videoFile(Request $request, ?string $current): ?string
    {
        $video = $current;

        if ($request->hasFile('video_file')) {
            $video = $request->file('video_file')->store('landing-pages/videos', 'uploads');
        } elseif ($request->boolean('remove_video_file')) {
            $video = null;
        }

        if ($current && $current !== $video) {
            Storage::disk('uploads')->delete($current);
        }

        return $video;
    }

    /**
     * @return list<array{type: string, value: string, label: ?string}>
     */
    private function supportChannels(array $rows): array
    {
        $channels = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            if (blank($row['type'] ?? null)) {
                continue;
            }

            if ($error = ContactChannel::validationError($row['type'], trim((string) $row['value']))) {
                $errors["support_channels.{$index}.value"] = $error;
                continue;
            }

            $channels[] = ContactChannel::fromArray($row)->toArray();
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $channels;
    }
}

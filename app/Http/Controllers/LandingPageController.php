<?php

namespace App\Http\Controllers;

use App\Models\Idea;
use App\Models\LandingPage;
use App\Support\Slug;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LandingPageController extends Controller
{
    private const MAX_FEATURES = 12;

    private const MAX_FAQ = 8;

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
            'is_active' => true,
        ]);

        return redirect()
            ->route('ideas.show', $idea)
            ->with('success', "صفحه فرود «{$landingPage->name}» ساخته و منتشر شد.");
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
            'body_text' => ['nullable', 'string', 'max:5000'],
            'features' => ['nullable', 'string', 'max:3000'],
            'faq' => ['nullable', 'array', 'max:' . self::MAX_FAQ],
            'faq.*.q' => ['nullable', 'string', 'max:255'],
            'faq.*.a' => ['nullable', 'string', 'max:2000', 'required_with:faq.*.q'],
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
        ];
    }

    private function buildContent(
        Request $request,
        array $data,
        array $current
    ): array {
        $features = collect(preg_split('/\R/u', $data['features'] ?? ''))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();

        if ($features->count() > self::MAX_FEATURES) {
            throw ValidationException::withMessages([
                'features' => 'حداکثر ' . self::MAX_FEATURES . ' ویژگی مجاز است.',
            ]);
        }

        if ($features->contains(fn ($line) => mb_strlen($line) > 200)) {
            throw ValidationException::withMessages([
                'features' => 'هر ویژگی حداکثر ۲۰۰ کاراکتر باشد.',
            ]);
        }

        $faq = collect($data['faq'] ?? [])
            ->map(fn ($item) => [
                'q' => trim((string) ($item['q'] ?? '')),
                'a' => trim((string) ($item['a'] ?? '')),
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

        return [
            'body_title' => $data['body_title'] ?? null,
            'body_text' => $data['body_text'] ?? null,
            'features' => $features->all(),
            'faq' => $faq,
            'hero_image' => $heroImage,
            'show_lead_form' => $request->boolean('show_lead_form'),
            'show_preorder_form' => $request->boolean('show_preorder_form'),
        ];
    }
}

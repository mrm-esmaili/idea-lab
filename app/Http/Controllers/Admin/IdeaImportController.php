<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Idea;
use App\Services\Import\IdeaImporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Throwable;

/**
 * ورود ایده‌ها از فایل ساخته‌شده با هوش مصنوعی (نسخه ۱.۲):
 * بارگذاری ← پیش‌نمایش و خطاها ← تأیید و ساخت همه‌با‌هم.
 */
class IdeaImportController extends Controller
{
    private const MAX_KB = 2048;

    private const KEEP_HOURS = 24;

    public function index()
    {
        return view('admin.imports.index', ['maxKb' => self::MAX_KB]);
    }

    public function upload(Request $request, IdeaImporter $importer)
    {
        $request->validate([
            'file' => ['nullable', 'required_without:text', 'file', 'max:' . self::MAX_KB],
            'text' => ['nullable', 'required_without:file', 'string', 'max:' . (self::MAX_KB * 1024)],
        ], [
            'file.required_without' => 'فایل را انتخاب کنید یا متن آن را در کادر بچسبانید.',
            'text.required_without' => 'فایل را انتخاب کنید یا متن آن را در کادر بچسبانید.',
        ]);

        if ($request->hasFile('file') && ! in_array(strtolower($request->file('file')->getClientOriginalExtension()), ['md', 'json', 'txt', 'markdown'], true)) {
            return back()->withErrors(['file' => 'فایل باید با پسوند md، json یا txt باشد.']);
        }

        $text = $request->hasFile('file') ? (string) file_get_contents($request->file('file')->getRealPath()) : (string) $request->input('text');

        if (! mb_check_encoding($text, 'UTF-8')) {
            return back()->withErrors(['file' => 'کدگذاری فایل UTF-8 نیست؛ فایل را با UTF-8 ذخیره کنید.']);
        }

        $parsed = $importer->parse($text);

        if ($parsed['ideas'] === []) {
            return back()->withInput()->withErrors(['file' => $parsed['errors'][0] ?? 'ایده‌ای در فایل پیدا نشد.']);
        }

        $this->prune();
        $token = (string) Str::uuid();
        File::ensureDirectoryExists($this->directory(), 0750);
        File::put($this->path($token), json_encode([
            'user_id' => $request->user()->id,
            'file' => $request->hasFile('file') ? $request->file('file')->getClientOriginalName() : null,
            'parse_errors' => $parsed['errors'],
            'ideas' => $parsed['ideas'],
        ], JSON_UNESCAPED_UNICODE));

        return redirect()->route('admin.imports.preview', $token);
    }

    public function preview(Request $request, string $token, IdeaImporter $importer)
    {
        $stored = $this->load($request, $token);
        $decisions = $this->decisions($request);
        $plans = $importer->preview($stored['ideas'], $decisions);

        return view('admin.imports.preview', [
            'token' => $token,
            'decisions' => $decisions,
            'file' => $stored['file'],
            'parseErrors' => $stored['parse_errors'],
            'plans' => $plans,
            'valid' => count(array_filter($plans, fn ($plan) => $plan['errors'] === [])),
        ]);
    }

    public function confirm(Request $request, string $token, IdeaImporter $importer)
    {
        $stored = $this->load($request, $token);

        try {
            $results = $importer->import($stored['ideas'], $request->user(), $request->boolean('skip_invalid'), $this->decisions($request));
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['import' => 'ورود انجام نشد و هیچ موردی ساخته نشد: ' . $e->getMessage()]);
        }

        File::delete($this->path($token));

        $created = collect($results)->where('mode', 'create')->count();
        $updated = collect($results)->where('mode', 'update')->count();

        return redirect()
            ->route('admin.imports.done')
            ->with('imported', collect($results)->map(fn ($result) => ['id' => $result['idea']->id, 'mode' => $result['mode']])->all())
            ->with('success', trim(
                ($created ? number_format($created) . ' ایده جدید ساخته شد. ' : '')
                . ($updated ? number_format($updated) . ' ایده موجود به‌روزرسانی شد. ' : '')
                . 'صفحه‌های جدید منتشرنشده‌اند؛ بازبینی کنید و تصویر و ویدیو بگذارید.'
            ));
    }

    public function done(Request $request)
    {
        $imported = collect((array) $request->session()->get('imported', []));

        return view('admin.imports.done', [
            'ideas' => Idea::whereIn('id', $imported->pluck('id'))->with(['landingPages.campaigns'])->orderBy('id')->get(),
            'modes' => $imported->pluck('mode', 'id')->all(),
        ]);
    }

    public function guide()
    {
        return response()->download(resource_path('import/ai-prompt.md'), 'idealab-ai-prompt.md', ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /**
     * فهرست ایده‌های فعلی برای پرامپت، تا هوش مصنوعی همان نام و نامک را بنویسد
     * و ورود، ایده‌ها را به‌روزرسانی کند نه تکراری بسازد.
     */
    public function existing()
    {
        $lines = ["# ایده‌های فعلی ایده‌لب", '', 'برای به‌روزرسانی این ایده‌ها، «name» و «slug» را دقیقاً همین‌طور بنویس:', ''];

        foreach (Idea::whereNull('archived_at')->with('landingPages:id,idea_id,slug')->orderBy('id')->get() as $idea) {
            $pages = $idea->landingPages->pluck('slug')->join('، ');
            $lines[] = "- name: \"{$idea->name}\" — slug: \"{$idea->slug}\"" . ($pages ? " — landing_page.slug: \"{$pages}\"" : ' — (بدون صفحه فرود)');
        }

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type' => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="idealab-existing-ideas.md"',
        ]);
    }

    public function sample()
    {
        return response()->download(resource_path('import/sample.md'), 'idealab-import-sample.md', ['Content-Type' => 'text/markdown; charset=UTF-8']);
    }

    /**
     * انتخاب مدیر برای هر ایده: «new» یا شناسه ایده موجود.
     *
     * @return array<int, string|int>
     */
    private function decisions(Request $request): array
    {
        return collect((array) $request->input('decision', []))
            ->filter(fn ($value, $key) => ctype_digit((string) $key) && ($value === 'new' || ctype_digit((string) $value)))
            ->map(fn ($value) => $value === 'new' ? 'new' : (int) $value)
            ->all();
    }

    private function load(Request $request, string $token): array
    {
        abort_unless(Str::isUuid($token) && is_file($this->path($token)), 404);

        $stored = json_decode((string) File::get($this->path($token)), true);

        // فقط کسی که فایل را بارگذاری کرده آن را می‌بیند
        abort_unless(is_array($stored) && ($stored['user_id'] ?? null) === $request->user()->id, 404);

        return $stored;
    }

    private function prune(): void
    {
        foreach (glob($this->directory() . '/*.json') ?: [] as $file) {
            if (filemtime($file) < now()->subHours(self::KEEP_HOURS)->getTimestamp()) {
                @unlink($file);
            }
        }
    }

    private function directory(): string
    {
        return storage_path('app/imports');
    }

    private function path(string $token): string
    {
        return $this->directory() . '/' . $token . '.json';
    }
}

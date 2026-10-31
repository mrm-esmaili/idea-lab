<?php

namespace App\Services\Updates;

use App\Support\AppVersion;
use App\Support\Format;
use ZipArchive;

/**
 * بسته به‌روزرسانی باز و بررسی‌شده (مرحله ۱۰۴). هر بسته‌ای که از open() عبور
 * کند: manifest معتبر دارد، هیچ مسیر خطرناک یا پیوند نمادینی ندارد، و همه
 * فایل‌هایش دقیقاً همان فایل‌های manifest با همان SHA-256 هستند.
 */
final class UpdatePackage
{
    private function __construct(
        public readonly string $path,
        private readonly ZipArchive $zip,
        private readonly array $manifest,
        private readonly int $bytes,
    ) {
    }

    public static function open(string $path): self
    {
        if (! class_exists(ZipArchive::class)) {
            throw new UpdateException('افزونه zip در PHP هاست فعال نیست (cPanel ← Select PHP Version ← Extensions ← zip).');
        }

        $zip = new ZipArchive;
        if (! is_file($path) || $zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new UpdateException('فایل zip معتبر نیست یا ناقص بارگذاری شده است.');
        }

        $manifest = json_decode((string) $zip->getFromName(ReleaseBuilder::MANIFEST), true);
        self::validateManifest($manifest);

        $files = $manifest['files'];
        $seen = 0;
        $bytes = 0;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            if (str_ends_with($name, '/')) {
                if (! PackagePaths::isSafe(rtrim($name, '/'))) {
                    throw new UpdateException('بسته شامل مسیر غیرمجاز است: ' . $name);
                }

                continue;
            }

            if ($name === ReleaseBuilder::MANIFEST) {
                continue;
            }

            if (! PackagePaths::isSafe($name)) {
                throw new UpdateException('بسته شامل مسیر غیرمجاز است: ' . $name);
            }

            $zip->getExternalAttributesIndex($i, $opsys, $attributes);
            if ($opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000) {
                throw new UpdateException('بسته شامل پیوند نمادین است: ' . $name);
            }

            if (! isset($files[$name])) {
                throw new UpdateException('فایل خارج از فهرست manifest در بسته است: ' . $name);
            }

            $content = $zip->getFromIndex($i);
            if ($content === false || ! hash_equals($files[$name], hash('sha256', $content))) {
                throw new UpdateException('فایل ' . $name . ' با manifest یکسان نیست؛ بسته خراب یا دست‌کاری شده است.');
            }

            $seen++;
            $bytes += $stat['size'];
        }

        if ($seen !== count($files)) {
            throw new UpdateException('بسته ناقص است: ' . (count($files) - $seen) . ' فایل manifest در آن نیست.');
        }

        if (trim((string) $zip->getFromName('VERSION')) !== $manifest['version']) {
            throw new UpdateException('فایل VERSION با نسخه manifest یکسان نیست.');
        }

        if (version_compare(PHP_VERSION, $manifest['min_php'], '<')) {
            throw new UpdateException('این نسخه دست‌کم PHP ' . Format::ltr($manifest['min_php']) . ' لازم دارد؛ نسخه فعلی ' . Format::ltr(PHP_VERSION) . ' است.');
        }

        return new self($path, $zip, $manifest, $bytes);
    }

    private static function validateManifest(mixed $manifest): void
    {
        $valid = is_array($manifest)
            && ($manifest['name'] ?? null) === 'idea-lab'
            && is_string($manifest['version'] ?? null) && preg_match(AppVersion::PATTERN, $manifest['version'])
            && is_string($manifest['min_php'] ?? null)
            && is_bool($manifest['vendor_included'] ?? null)
            && is_string($manifest['composer_lock_sha256'] ?? null)
            && is_array($manifest['migrations'] ?? null)
            && is_array($manifest['files'] ?? null)
            && isset($manifest['files']['artisan'], $manifest['files']['public/index.php'], $manifest['files']['VERSION']);

        if (! $valid) {
            throw new UpdateException('این فایل بسته به‌روزرسانی ایده‌لب نیست (manifest.json معتبر ندارد).');
        }

        foreach ($manifest['files'] as $path => $hash) {
            if (! is_string($path) || ! PackagePaths::isSafe($path) || ! is_string($hash) || ! preg_match('/^[0-9a-f]{64}$/', $hash)) {
                throw new UpdateException('manifest شامل مسیر یا چک‌سام نامعتبر است.');
            }
        }
    }

    public function version(): string
    {
        return $this->manifest['version'];
    }

    /**
     * @return array<string, string> مسیر => SHA-256
     */
    public function files(): array
    {
        return $this->manifest['files'];
    }

    public function vendorIncluded(): bool
    {
        return $this->manifest['vendor_included'];
    }

    public function composerLockHash(): string
    {
        return $this->manifest['composer_lock_sha256'];
    }

    /**
     * @return list<string>
     */
    public function migrations(): array
    {
        return $this->manifest['migrations'];
    }

    public function builtAt(): ?string
    {
        return $this->manifest['built_at'] ?? null;
    }

    public function commit(): ?string
    {
        return $this->manifest['commit'] ?? null;
    }

    public function bytes(): int
    {
        return $this->bytes;
    }

    public function sha256(): string
    {
        return hash_file('sha256', $this->path);
    }

    public function content(string $file): string
    {
        $content = $this->zip->getFromName($file);

        if ($content === false) {
            throw new UpdateException('خواندن ' . $file . ' از بسته ناموفق بود.');
        }

        return $content;
    }

    public function manifestJson(): string
    {
        return json_encode($this->manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

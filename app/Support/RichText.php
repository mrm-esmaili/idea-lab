<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * متن قالب‌دار بخش‌های توضیحی صفحه فرود (نسخه ۱.۱.۱).
 *
 * فقط فهرست سفید تگ‌ها نگه داشته می‌شود و هیچ ویژگی (attribute) به‌جز href
 * پیوند با http/https/mailto/tel باقی نمی‌ماند؛ پس اسکریپت، style، رویداد و
 * iframe هرگز به صفحه عمومی نمی‌رسد. هم هنگام ذخیره و هم هنگام نمایش اجرا می‌شود.
 */
final class RichText
{
    /** تگ‌های مجاز */
    public const TAGS = ['p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'hr', 'blockquote', 'strong', 'b', 'em', 'i', 'u', 's', 'a'];

    /** تگ‌هایی که با محتوایشان حذف می‌شوند (نه فقط پوسته) */
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'head', 'title', 'meta', 'link', 'form', 'input', 'textarea', 'select', 'button', 'img', 'video', 'audio', 'canvas'];

    /**
     * @return string|null HTML امن، یا null اگر متنی نماند
     */
    public static function sanitize(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body><div id="rich-root">' . $html . '</div></body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('rich-root');
        if (! $root) {
            return null;
        }

        self::clean($root);

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        $output = trim($output);

        // فقط تگ خالی (مثلاً <p><br></p>) یعنی متنی وارد نشده است
        return trim(strip_tags($output, '<hr>')) === '' ? null : $output;
    }

    /**
     * متن ساده نسخه‌های قبل ← HTML (پاراگراف با خط خالی، br با خط جدید).
     */
    public static function fromPlain(?string $text): ?string
    {
        $text = trim(str_replace("\r\n", "\n", (string) $text));

        if ($text === '') {
            return null;
        }

        return implode('', array_map(
            fn ($paragraph) => '<p>' . nl2br(e(trim($paragraph)), false) . '</p>',
            preg_split('/\n{2,}/', $text)
        ));
    }

    /**
     * HTML امن برای نمایش، با اعداد فارسی فقط در متن (نه در href).
     */
    public static function render(?string $html, bool $persianDigits = true): string
    {
        $html = self::sanitize($html);

        if ($html === null || ! $persianDigits) {
            return (string) $html;
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8"><body><div id="rich-root">' . $html . '</div></body>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('rich-root');
        self::eachText($root, fn (DOMNode $node) => $node->nodeValue = Format::faDigits($node->nodeValue));

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    /**
     * متن خالص برای شمارش طول یا پیش‌نمایش.
     */
    public static function plain(?string $html): string
    {
        return trim(html_entity_decode(strip_tags(str_replace(['<br>', '</p>', '</li>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'], "\n", (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function clean(DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                // توضیح، CDATA و دستورالعمل پردازش
                $node->removeChild($child);
                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            self::clean($child);

            if (! in_array($tag, self::TAGS, true)) {
                // تگ ناشناخته (div، span، font و ...): پوسته حذف، محتوا می‌ماند
                $wrapper = in_array($tag, ['div', 'section', 'article'], true) && $child->hasChildNodes();
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                if ($wrapper) {
                    $node->insertBefore($node->ownerDocument->createElement('br'), $child);
                }
                $node->removeChild($child);
                continue;
            }

            self::cleanAttributes($child, $tag);

            if ($tag === 'a' && ! $child->hasAttribute('href')) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
            }

            // بند کاملاً خالی (Enter پشت‌سرهم)؛ بند دارای <br> عمداً خط خالی است و می‌ماند
            if (! in_array($tag, ['br', 'hr'], true) && $child->parentNode === $node && trim($child->textContent) === ''
                && $child->getElementsByTagName('br')->length === 0 && $child->getElementsByTagName('hr')->length === 0) {
                $node->removeChild($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $element, string $tag): void
    {
        $href = $tag === 'a' ? trim($element->getAttribute('href')) : null;

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }

        if ($href === null || $href === '' || ! preg_match('#^(https?://|mailto:|tel:)#i', $href) || preg_match('/[\x00-\x20"\'<>]/', $href)) {
            return;
        }

        $element->setAttribute('href', $href);

        if (preg_match('#^https?://#i', $href)) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener nofollow');
        }
    }

    private static function eachText(DOMNode $node, callable $callback): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            $child->nodeType === XML_TEXT_NODE ? $callback($child) : self::eachText($child, $callback);
        }
    }
}

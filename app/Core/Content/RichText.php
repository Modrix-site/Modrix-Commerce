<?php

namespace App\Core\Content;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Formatted text written in the admin editor (product descriptions).
 *
 * Only a small allow-list of formatting tags survives; every attribute except
 * a link's href is removed. Older values saved as plain text stay plain and
 * are converted to paragraphs when shown.
 */
final class RichText
{
    private const ALLOWED = ['p', 'br', 'strong', 'em', 'u', 's', 'h2', 'h3', 'h4', 'ul', 'ol', 'li', 'blockquote', 'hr'];

    private const DROPPED = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'svg', 'math', 'form', 'textarea', 'select', 'button'];

    private const MAX_LENGTH = 60000;

    private static ?HtmlSanitizer $sanitizer = null;

    public static function isHtml(?string $value): bool
    {
        return $value !== null && preg_match('/<\/?[a-z][a-z0-9]*(\s[^>]*)?\/?>/i', $value) === 1;
    }

    /**
     * The value to store: cleaned HTML, plain text as typed, or null when empty.
     */
    public static function clean(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! self::isHtml($value)) {
            return $value;
        }

        $html = trim(self::sanitizer()->sanitize($value));

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5), " \t\n\r\0\x0B\u{A0}") === '' && ! str_contains($html, '<hr')
            ? null
            : $html;
    }

    /**
     * Safe HTML for display. Plain text is escaped and split into paragraphs.
     */
    public static function toHtml(?string $value): ?string
    {
        $value = trim(str_replace("\r\n", "\n", (string) $value));

        if ($value === '') {
            return null;
        }

        if (self::isHtml($value)) {
            return self::clean($value);
        }

        return collect(preg_split('/\n{2,}/', $value))
            ->map(fn (string $paragraph) => '<p>'.str_replace("\n", '<br>', e(trim($paragraph))).'</p>')
            ->implode('');
    }

    /**
     * Plain text with one line per block, for themes and places that cannot show HTML.
     */
    public static function toText(?string $value): ?string
    {
        if ($value === null || ! self::isHtml($value)) {
            return $value;
        }

        $text = preg_replace(['/<br\s*\/?>/i', '/<\/(p|h[1-6]|li|blockquote)>/i'], "\n", $value);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5);

        return trim((string) preg_replace('/[ \t]*\n+/', "\n", $text));
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks()
            ->allowElement('a', ['href'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow')
            ->withMaxInputLength(self::MAX_LENGTH);

        foreach (self::ALLOWED as $element) {
            $config = $config->allowElement($element);
        }

        foreach (self::DROPPED as $element) {
            $config = $config->dropElement($element);
        }

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}

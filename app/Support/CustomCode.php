<?php

namespace App\Support;

use App\Core\Settings\GeneralSettings;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Settings → General → Custom code: the head and body-end snippets, rendered
 * on storefront pages only (checkout only when allowed). Script tags get the
 * page's CSP nonce so they run under the Content-Security-Policy.
 */
final class CustomCode
{
    public static function for(string $component, string $place): HtmlString
    {
        if (! str_starts_with($component, 'storefront/')) {
            return new HtmlString('');
        }

        try {
            $scripts = app(GeneralSettings::class)->scripts();
        } catch (Throwable) {
            return new HtmlString('');
        }

        if ($component === 'storefront/checkout' && ! $scripts['on_checkout']) {
            return new HtmlString('');
        }

        $snippet = $place === 'head'
            ? ($scripts['head_enabled'] ? $scripts['head'] : '')
            : ($scripts['body_enabled'] ? $scripts['body'] : '');

        if (trim($snippet) === '') {
            return new HtmlString('');
        }

        $nonce = Vite::cspNonce();
        if ($nonce !== null) {
            // Our nonce wins over any nonce attribute in the snippet.
            $snippet = (string) preg_replace('/<script\b(?![^>]*\snonce=)/i', '<script nonce="'.e($nonce).'"', $snippet);
        }

        return new HtmlString("\n<!-- Custom code (Settings → General) -->\n".$snippet."\n");
    }
}

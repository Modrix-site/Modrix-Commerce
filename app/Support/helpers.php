<?php

use App\Core\Localization\InterfaceTranslations;

if (! function_exists('st')) {
    /**
     * Translates a storefront text into the shopper's language (see
     * InterfaceTranslations). The key is the English text; placeholders look
     * like `:name`. Use string literals so the catalogue test finds them.
     *
     * @param  array<string, string|int|float>  $replace
     */
    function st(string $key, array $replace = []): string
    {
        return app(InterfaceTranslations::class)->get($key, $replace);
    }
}

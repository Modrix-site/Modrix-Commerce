<?php

namespace App\Core\Localization;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Translations of the storefront's own wording ("Add to cart", "Checkout"…).
 *
 * Keys are the English texts, listed in `lang/storefront/en.json` (the source
 * catalogue). Shipped translations live in `lang/storefront/{locale}.json`;
 * merchants override or complete them in the admin (stored in
 * `interface_translations`). Placeholders look like `:name`.
 */
final class InterfaceTranslations
{
    public const SOURCE_LOCALE = 'en';

    private const CACHE_PREFIX = 'ecomodular:interface-translations:';

    /** @var array<string, array<string, string>> */
    private array $loaded = [];

    /**
     * @return list<string> every translatable storefront text
     */
    public function keys(): array
    {
        return array_keys($this->file(self::SOURCE_LOCALE));
    }

    /**
     * Messages for a locale: shipped translations overlaid with the store's
     * own. Keys without a translation are left out (the UI shows the key).
     *
     * @return array<string, string>
     */
    public function messages(string $locale): array
    {
        if (isset($this->loaded[$locale])) {
            return $this->loaded[$locale];
        }

        $build = function () use ($locale): array {
            $source = $this->file(self::SOURCE_LOCALE);
            // The keys are English: English pages need no shipped messages.
            $messages = $locale === self::SOURCE_LOCALE ? [] : array_intersect_key($this->file($locale), $source);

            foreach ($this->overrides($locale) as $key => $value) {
                if (array_key_exists($key, $source)) {
                    $messages[$key] = $value;
                }
            }

            return array_filter($messages, fn (string $v) => $v !== '');
        };

        return $this->loaded[$locale] = Cache::rememberForever($this->cacheKey($locale), $build);
    }

    /**
     * @return array<string, string>
     */
    public function shipped(string $locale): array
    {
        return $this->file($locale);
    }

    /**
     * @return array<string, string>
     */
    public function overrides(string $locale): array
    {
        if (! Schema::hasTable('interface_translations')) {
            return [];
        }

        /** @var array<string, string> */
        return DB::table('interface_translations')->where('locale', $locale)->pluck('value', 'key')->all();
    }

    /**
     * Saves the store's own texts for a locale. An empty value removes the
     * override (the shipped translation, or English, is used again).
     *
     * @param  array<string, string|null>  $values
     */
    public function save(string $locale, array $values): void
    {
        $known = array_flip($this->keys());
        $now = now();

        foreach ($values as $key => $value) {
            if (! isset($known[$key])) {
                continue;
            }

            $value = is_string($value) ? trim($value) : '';

            if ($value === '') {
                DB::table('interface_translations')->where('locale', $locale)->where('key', $key)->delete();
            } else {
                DB::table('interface_translations')->updateOrInsert(
                    ['locale' => $locale, 'key' => $key],
                    ['value' => $value, 'updated_at' => $now, 'created_at' => $now],
                );
            }
        }

        $this->forget($locale);
    }

    public function forget(string $locale): void
    {
        unset($this->loaded[$locale]);
        Cache::forget($this->cacheKey($locale));
    }

    /**
     * Includes the catalogue files' modification times, so a deploy with
     * updated translations is picked up without clearing the cache.
     */
    private function cacheKey(string $locale): string
    {
        $version = (int) @filemtime(lang_path('storefront/en.json')).'-'.(int) @filemtime(lang_path("storefront/{$locale}.json"));

        return self::CACHE_PREFIX.$locale.':'.$version;
    }

    /**
     * Translates a storefront text for the current request (server-side
     * messages: toasts, page titles, stock notices).
     *
     * @param  array<string, string|int|float>  $replace
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $text = $locale === self::SOURCE_LOCALE && $this->overrides($locale) === []
            ? $key
            : ($this->messages($locale)[$key] ?? $key);

        foreach ($replace as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }

        return $text;
    }

    /**
     * @return array<string, string>
     */
    private function file(string $locale): array
    {
        if (! preg_match('/^[a-z]{2}$/', $locale)) {
            return [];
        }

        $path = lang_path("storefront/{$locale}.json");
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;

        return is_array($data) ? array_filter($data, 'is_string') : [];
    }
}

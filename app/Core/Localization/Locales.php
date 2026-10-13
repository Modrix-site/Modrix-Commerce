<?php

namespace App\Core\Localization;

use App\Core\Settings\SettingsRepository;

/**
 * Storefront languages: the store's default language (the language product
 * and category fields are written in) plus any number of additional
 * languages with translations. Codes are lowercase ISO 639-1.
 */
final class Locales
{
    /** Languages the store can enable (left-to-right scripts). */
    public const AVAILABLE = [
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'pt' => 'Português',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'sv' => 'Svenska',
        'da' => 'Dansk',
        'nb' => 'Norsk bokmål',
        'fi' => 'Suomi',
        'cs' => 'Čeština',
        'ro' => 'Română',
        'tr' => 'Türkçe',
        'el' => 'Ελληνικά',
        'ja' => '日本語',
        'zh' => '中文',
        'ko' => '한국어',
    ];

    public const DEFAULT_KEY = 'store.default_locale';

    public const EXTRA_KEY = 'store.locales';

    public function __construct(private readonly SettingsRepository $settings) {}

    public function default(): string
    {
        $locale = (string) $this->settings->get(self::DEFAULT_KEY, config('app.locale', 'en'));

        return array_key_exists($locale, self::AVAILABLE) ? $locale : 'en';
    }

    /**
     * Additional languages (translations), never including the default.
     *
     * @return list<string>
     */
    public function additional(): array
    {
        $extra = array_values(array_filter(
            (array) ($this->settings->get(self::EXTRA_KEY) ?? []),
            fn ($code) => is_string($code) && array_key_exists($code, self::AVAILABLE) && $code !== $this->default(),
        ));

        return array_values(array_unique($extra));
    }

    /**
     * @return list<string> default first
     */
    public function enabled(): array
    {
        return [$this->default(), ...$this->additional()];
    }

    public function isEnabled(string $locale): bool
    {
        return in_array($locale, $this->enabled(), true);
    }

    /**
     * @param  list<string>  $additional
     */
    public function update(string $default, array $additional): void
    {
        $this->settings->set(self::DEFAULT_KEY, $default);
        $this->settings->set(self::EXTRA_KEY, array_values(array_unique(array_diff($additional, [$default]))));
    }

    /**
     * True when the current request shows a language other than the default.
     */
    public function translating(): bool
    {
        return app()->getLocale() !== $this->default();
    }

    public static function name(string $code): string
    {
        return self::AVAILABLE[$code] ?? $code;
    }

    /**
     * @param  list<string>  $codes
     * @return list<array{code: string, name: string}>
     */
    public static function options(array $codes): array
    {
        return array_map(fn (string $code) => ['code' => $code, 'name' => self::name($code)], $codes);
    }
}

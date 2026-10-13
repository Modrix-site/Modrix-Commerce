<?php

namespace App\Core\Settings;

use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use Locale;
use ResourceBundle;

/**
 * ISO 3166-1 alpha-2 countries, sourced from the ICU data shipped with the
 * intl extension (a platform requirement). Display names follow the current
 * locale; `all()` stays English.
 */
final class Countries
{
    /**
     * Codes in ICU's region list that are not ISO 3166-1 assigned countries:
     * groupings, pseudo-regions and "exceptionally reserved" codes.
     */
    private const EXCLUDED = ['EU', 'EZ', 'UN', 'QO', 'XA', 'XB', 'ZZ', 'AC', 'CP', 'DG', 'EA', 'IC', 'TA'];

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, string> code => name, sorted by name
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $countries = [];
        $bundle = ResourceBundle::create('en', 'ICUDATA-region');
        $names = $bundle?->get('Countries');

        if ($names instanceof ResourceBundle) {
            foreach ($names as $code => $name) {
                if (is_string($code) && preg_match('/^[A-Z]{2}$/', $code) && ! in_array($code, self::EXCLUDED, true)) {
                    $countries[$code] = (string) $name;
                }
            }
        }

        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);

        return self::$cache = $countries;
    }

    /** The current locale, or English outside a full application (unit tests, early boot). */
    private static function locale(): string
    {
        $app = Container::getInstance();

        return $app instanceof Application ? $app->getLocale() : 'en';
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    /**
     * The country's name in the current language (English when the locale
     * is English or ICU has no name for it).
     */
    public static function name(string $code): string
    {
        $code = strtoupper($code);
        $english = self::all()[$code] ?? (Locale::getDisplayRegion('-'.$code, 'en') ?: $code);
        $locale = self::locale();

        if ($locale === 'en') {
            return $english;
        }

        $localized = Locale::getDisplayRegion('-'.$code, $locale);

        return is_string($localized) && $localized !== '' && $localized !== $code ? $localized : $english;
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (array_keys(self::all()) as $code) {
            $options[] = ['code' => $code, 'name' => self::name($code)];
        }

        // Sorted by the name shoppers read.
        $collator = class_exists(\Collator::class) ? new \Collator(self::locale()) : null;
        usort($options, fn (array $a, array $b) => $collator ? (int) $collator->compare($a['name'], $b['name']) : strcmp($a['name'], $b['name']));

        return $options;
    }
}

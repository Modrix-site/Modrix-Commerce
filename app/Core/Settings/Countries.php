<?php

namespace App\Core\Settings;

use Locale;
use ResourceBundle;

/**
 * ISO 3166-1 alpha-2 countries with English display names, sourced from the
 * ICU data shipped with the intl extension (a platform requirement).
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

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function name(string $code): string
    {
        return self::all()[strtoupper($code)] ?? (Locale::getDisplayRegion('-'.$code, 'en') ?: $code);
    }

    /**
     * @return list<array{code: string, name: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $code => $name) {
            $options[] = ['code' => $code, 'name' => $name];
        }

        return $options;
    }
}

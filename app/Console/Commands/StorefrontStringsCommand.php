<?php

namespace App\Console\Commands;

use App\Core\Localization\InterfaceTranslations;
use App\Core\Localization\Locales;
use App\Core\Localization\StorefrontStrings;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rebuilds lang/storefront/en.json from the code and reports how complete
 * the shipped translations are. Run it after adding storefront texts.
 */
#[Signature('ecomodular:storefront-strings')]
#[Description('Extract storefront texts into lang/storefront/en.json and report translation coverage')]
class StorefrontStringsCommand extends Command
{
    public function handle(StorefrontStrings $strings, InterfaceTranslations $translations): int
    {
        $count = $strings->writeSource();
        $translations->forget('en');
        $this->info("{$count} storefront texts written to lang/storefront/en.json.");

        $keys = $translations->keys();
        foreach (array_keys(Locales::AVAILABLE) as $locale) {
            if ($locale === 'en') {
                continue;
            }

            $shipped = $translations->shipped($locale);
            if ($shipped === []) {
                continue;
            }

            $missing = array_diff($keys, array_keys($shipped));
            $this->line(sprintf('%s: %d/%d translated%s', $locale, count($keys) - count($missing), count($keys), $missing === [] ? '' : ' (missing: '.implode(' | ', array_slice($missing, 0, 5)).(count($missing) > 5 ? ' …' : '').')'));
            $translations->forget($locale);
        }

        return self::SUCCESS;
    }
}

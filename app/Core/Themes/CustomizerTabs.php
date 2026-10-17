<?php

namespace App\Core\Themes;

/**
 * Which customizer tab a design setting belongs to. A field may name its tab;
 * per-area style fields ("style.{area}.…") follow their area; everything else
 * is placed by its group name.
 */
final class CustomizerTabs
{
    /** Tab key => label, in display order. */
    public const TABS = [
        'general' => 'General',
        'header' => 'Header',
        'home' => 'Homepage',
        'catalog' => 'Catalog',
        'product' => 'Product',
        'cart' => 'Cart',
        'checkout' => 'Checkout',
        'footer' => 'Footer',
    ];

    /** Group-name keywords per tab, checked in this order. */
    private const KEYWORDS = [
        'home' => ['hero', 'categor', 'promotion', 'collection', 'campaign', 'brand story', 'shortcut', 'assurance', 'homepage', 'home'],
        'catalog' => ['catalog', 'listing', 'card'],
        'product' => ['product'],
        'cart' => ['cart', 'checkout'],
        'header' => ['header', 'announcement', 'navigation', 'menu'],
        'footer' => ['footer', 'contact'],
    ];

    /**
     * @param  array<string, mixed>  $field
     */
    public static function for(string $key, array $field): string
    {
        $explicit = $field['tab'] ?? null;

        if (is_string($explicit) && array_key_exists($explicit, self::TABS)) {
            return $explicit;
        }

        if (preg_match('/^style\.([a-z]+)\./', $key, $match) === 1) {
            return array_key_exists($match[1], self::TABS) ? $match[1] : 'general';
        }

        $group = mb_strtolower((string) ($field['group'] ?? ''));

        foreach (self::KEYWORDS as $tab => $words) {
            foreach ($words as $word) {
                if (str_contains($group, $word)) {
                    return $tab;
                }
            }
        }

        return 'general';
    }
}

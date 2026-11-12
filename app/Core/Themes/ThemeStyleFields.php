<?php

namespace App\Core\Themes;

/**
 * Store-wide and per-page styles a merchant can set in the customizer:
 * headings, body text, buttons and prices. Keys are "style.{area}.{prop}".
 *
 * Only values the merchant set produce CSS, so an unconfigured store looks
 * exactly as its theme designed it. General rules apply to `.storefront`;
 * page rules to `.storefront[data-page="{area}"]` and, being more specific,
 * override General. Rules target the core classes (store-button, store-price,
 * store-price-sale) and the bundled themes' own button and price classes, and
 * use !important so a merchant's choice beats the theme stylesheet.
 */
final class ThemeStyleFields
{
    public const AREAS = ['general', 'home', 'catalog', 'product', 'cart'];

    public const AREA_LABELS = ['general' => 'Store-wide', 'home' => 'Homepage', 'catalog' => 'Catalog', 'product' => 'Product page', 'cart' => 'Cart and checkout'];

    public const GROUP = 'Fonts and buttons';

    public const HEADING_SIZES = ['20', '24', '28', '32', '36', '40', '48', '56', '64'];

    public const BODY_SIZES = ['13', '14', '15', '16', '17', '18'];

    public const BUTTON_SIZES = ['12', '13', '14', '15', '16', '18'];

    public const PRICE_SIZES = ['14', '16', '18', '20', '24', '28'];

    public const WEIGHTS = ['400', '500', '600', '700', '800'];

    /** Elements and classes each kind of rule targets. */
    private const BUTTONS = ['.store-button', '.hearth-primary', '.vellaro-primary', '.vellaro-card-buy', '.electronics-button'];

    private const PRICES = ['.store-price', '.hearth-product-price', '.hearth-card-info strong', '.vellaro-product-price', '.vellaro-card-info strong', '.electronics-purchase-price strong', '.electronics-card-price strong', '.ecom-price'];

    /** Property names of one area. */
    private const PROPS = ['heading_font', 'heading_size', 'heading_weight', 'heading_color', 'body_font', 'body_size', 'body_color', 'button_bg', 'button_text', 'button_hover_bg', 'button_font', 'button_size', 'button_radius', 'price_size', 'price_color', 'price_sale_color'];

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (self::AREAS as $area) {
            $prefix = "style.{$area}.";
            $where = self::AREA_LABELS[$area];
            $base = ['group' => self::GROUP, 'tab' => $area];

            $fields += [
                "{$prefix}heading_font" => ['type' => 'font', 'label' => "Headings font ({$where})"] + $base,
                "{$prefix}heading_size" => ['type' => 'select', 'label' => "Page title size, px ({$where})", 'options' => self::HEADING_SIZES] + $base,
                "{$prefix}heading_weight" => ['type' => 'select', 'label' => "Headings weight ({$where})", 'options' => self::WEIGHTS] + $base,
                "{$prefix}heading_color" => ['type' => 'color', 'label' => "Headings colour ({$where})"] + $base,
                "{$prefix}body_font" => ['type' => 'font', 'label' => "Text font ({$where})"] + $base,
                "{$prefix}body_size" => ['type' => 'select', 'label' => "Text size, px ({$where})", 'options' => self::BODY_SIZES] + $base,
                "{$prefix}body_color" => ['type' => 'color', 'label' => "Text colour ({$where})"] + $base,
                "{$prefix}button_bg" => ['type' => 'color', 'label' => "Button background ({$where})"] + $base,
                "{$prefix}button_text" => ['type' => 'color', 'label' => "Button text colour ({$where})"] + $base,
                "{$prefix}button_hover_bg" => ['type' => 'color', 'label' => "Button hover background ({$where})"] + $base,
                "{$prefix}button_font" => ['type' => 'font', 'label' => "Button font ({$where})"] + $base,
                "{$prefix}button_size" => ['type' => 'select', 'label' => "Button text size, px ({$where})", 'options' => self::BUTTON_SIZES] + $base,
                "{$prefix}button_radius" => ['type' => 'select', 'label' => "Button corners ({$where})", 'options' => ThemeSchema::RADII] + $base,
                "{$prefix}price_size" => ['type' => 'select', 'label' => "Price size, px ({$where})", 'options' => self::PRICE_SIZES] + $base,
                "{$prefix}price_color" => ['type' => 'color', 'label' => "Price colour ({$where})"] + $base,
                "{$prefix}price_sale_color" => ['type' => 'color', 'label' => "Sale price colour ({$where})"] + $base,
            ];
        }

        return $fields;
    }

    /**
     * CSS for the style values that are set (empty when none are).
     *
     * @param  array<string, mixed>  $design  validated design values
     */
    public static function css(array $design): string
    {
        $css = '';
        $general = self::values($design, 'general');

        foreach (self::AREAS as $area) {
            $scope = $area === 'general' ? '.storefront' : '.storefront[data-page="'.$area.'"]';
            $own = self::values($design, $area);

            // A page that sets nothing follows the General rules. A page that
            // sets something repeats General's other values in its own (more
            // specific) scope, so e.g. General's sale or hover colour still applies.
            if ($area !== 'general' && $own === []) {
                continue;
            }

            $values = $area === 'general' ? $own : $own + $general;
            $value = fn (string $prop): ?string => $values[$prop] ?? null;

            $size = $value('heading_size');
            $heading = self::declarations([
                'font-family' => self::font($value('heading_font')),
                'font-weight' => $value('heading_weight'),
                'color' => $value('heading_color'),
            ]);
            $css .= self::rule($scope, ['h1', 'h2', 'h3'], $heading);
            // The chosen size is the desktop size; titles shrink on small screens.
            $css .= self::rule($scope, ['h1'], self::declarations(['font-size' => $size === null ? null : self::fluid("{$size}px")]));
            $css .= self::rule($scope, ['h2'], self::declarations(['font-size' => $size === null ? null : self::fluid("{$size}px * 0.75")]));
            $css .= self::rule($scope, ['h3'], self::declarations(['font-size' => $size === null ? null : self::fluid("{$size}px * 0.6")]));

            $bodySize = $value('body_size');
            $css .= self::rule($scope, [''], self::declarations([
                'font-family' => self::font($value('body_font')),
                'font-size' => $bodySize === null ? null : "{$bodySize}px",
                'color' => $value('body_color'),
            ]));

            $buttonSize = $value('button_size');
            $css .= self::rule($scope, self::BUTTONS, self::declarations([
                'background' => $value('button_bg'),
                'color' => $value('button_text'),
                'font-family' => self::font($value('button_font')),
                'font-size' => $buttonSize === null ? null : "{$buttonSize}px",
                'border-radius' => $value('button_radius'),
            ]));
            // Outline buttons (e.g. Buy now) keep their outline in the button colour.
            $css .= self::rule($scope, ['.store-button-outline'], self::declarations([
                'border-color' => $value('button_bg'),
                'color' => $value('button_bg'),
                'font-family' => self::font($value('button_font')),
                'font-size' => $buttonSize === null ? null : "{$buttonSize}px",
                'border-radius' => $value('button_radius'),
            ]));
            $css .= self::rule($scope, ['.store-button-outline:hover'], self::declarations([
                'background' => $value('button_bg'),
                'color' => $value('button_bg') === null ? null : ($value('button_text') ?? '#ffffff'),
            ]));
            $hover = $value('button_hover_bg');
            $css .= self::rule($scope, array_map(fn (string $s) => "{$s}:hover", self::BUTTONS), self::declarations([
                'background' => $hover,
                // A set background beats the theme's own hover; keep some feedback.
                'filter' => $hover === null && $value('button_bg') !== null ? 'brightness(0.92)' : null,
            ]));

            $priceSize = $value('price_size');
            $css .= self::rule($scope, self::PRICES, self::declarations([
                'font-size' => $priceSize === null ? null : "{$priceSize}px",
                'color' => $value('price_color'),
            ]));
            $css .= self::rule($scope, ['.store-price-sale'], self::declarations([
                'color' => $value('price_sale_color'),
            ]));
        }

        return $css;
    }

    /**
     * The set (and safe) style values of one area, by property.
     *
     * @param  array<string, mixed>  $design
     * @return array<string, string>
     */
    private static function values(array $design, string $area): array
    {
        $values = [];

        foreach (self::PROPS as $prop) {
            $clean = self::clean($design["style.{$area}.{$prop}"] ?? null);

            if ($clean !== null) {
                $values[$prop] = $clean;
            }
        }

        return $values;
    }

    /**
     * @param  list<string>  $targets  '' targets the scope itself
     */
    private static function rule(string $scope, array $targets, string $declarations): string
    {
        if ($declarations === '') {
            return '';
        }

        $selectors = array_map(fn (string $target) => $target === '' ? $scope : "{$scope} {$target}", $targets);

        return implode(',', $selectors).'{'.$declarations.'}';
    }

    /**
     * @param  array<string, string|null>  $properties
     */
    private static function declarations(array $properties): string
    {
        $out = [];

        foreach ($properties as $property => $value) {
            if ($value !== null) {
                $out[] = "{$property}:{$value}!important";
            }
        }

        return implode(';', $out);
    }

    /** A size that is $max on wide screens and shrinks to 60% on small ones. */
    private static function fluid(string $max): string
    {
        $full = str_contains($max, '*') ? "calc({$max})" : $max;

        return "clamp(calc({$max} * 0.6), calc({$max} * 0.45 + 2vw), {$full})";
    }

    private static function font(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (str_starts_with($value, 'custom:')) {
            return '"'.substr($value, 7).'", '.ThemeSchema::FONT_STACKS['sans'];
        }

        return ThemeSchema::FONT_STACKS[$value] ?? null;
    }

    /** Values are validated on save; never let CSS syntax through regardless. */
    private static function clean(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || preg_match('/[{};<>\\\\]/', $value) === 1) {
            return null;
        }

        return $value;
    }
}

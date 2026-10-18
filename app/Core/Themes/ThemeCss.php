<?php

namespace App\Core\Themes;

/**
 * Builds the storefront stylesheet from validated design values. Only
 * values that passed ThemeSchema validation (hex colors, listed radii, known
 * font stacks, validated family names and asset URLs) are interpolated, so
 * the output cannot break out of its declarations.
 */
final class ThemeCss
{
    private const SPACING = ['compact' => '2rem', 'comfortable' => '3rem', 'spacious' => '4.5rem'];

    private const CONTAINER = ['narrow' => '60rem', 'default' => '72rem', 'reference' => '80rem', 'wide' => '84rem'];

    public static function build(ResolvedTheme $theme): string
    {
        $design = $theme->design;
        $css = '';

        foreach ($theme->manifest->fonts as $font) {
            $url = $theme->assetUrl($font['file']);
            $css .= sprintf(
                '@font-face{font-family:"%s";src:url("%s") format("woff2");font-weight:%s;font-style:%s;font-display:swap}',
                $font['family'],
                addcslashes($url, '"\\'),
                $font['weight'],
                $font['style'],
            );
        }

        $light = self::colors(fn (string $key) => (string) $design["color.{$key}"]);
        $dark = $theme->manifest->darkColors === []
            ? $light
            : self::colors(fn (string $key) => (string) ($theme->manifest->darkColors[$key] ?? $design["color.{$key}"]));

        $shared = [
            '--radius' => (string) $design['radius'],
            '--font-sans' => self::font((string) $design['font_body']),
            '--font-heading' => self::font((string) $design['font_heading']),
            '--store-section-gap' => self::SPACING[$design['density']] ?? self::SPACING['comfortable'],
            '--store-container' => self::CONTAINER[$design['container']] ?? self::CONTAINER['default'],
            '--store-search' => (string) $design['color.search'],
            '--store-sale' => (string) $design['color.sale'],
        ];

        $css .= ':root{'.self::declarations($light + $shared).'}';
        $css .= '.dark{'.self::declarations($dark).'}';
        $css .= '.storefront h1,.storefront h2,.storefront h3{font-family:var(--font-heading)}';
        // Fonts, prices and buttons the merchant set in the customizer (nothing when unset).
        $css .= ThemeStyleFields::css($design);

        return $css;
    }

    /**
     * Theme colors mapped onto the design system's variables (both the raw
     * variables and Tailwind's --color-* aliases, which are resolved at :root).
     *
     * @param  callable(string): string  $color
     * @return array<string, string>
     */
    private static function colors(callable $color): array
    {
        $map = [
            'background' => $color('background'),
            'foreground' => $color('foreground'),
            'card' => $color('background'),
            'card-foreground' => $color('foreground'),
            'popover' => $color('background'),
            'popover-foreground' => $color('foreground'),
            'primary' => $color('primary'),
            'primary-foreground' => $color('primary_foreground'),
            'secondary' => $color('muted'),
            'secondary-foreground' => $color('foreground'),
            'muted' => $color('muted'),
            'muted-foreground' => $color('muted_foreground'),
            'accent' => $color('accent'),
            'accent-foreground' => $color('accent_foreground'),
            'border' => $color('border'),
            'input' => $color('border'),
            'ring' => $color('primary'),
        ];

        $vars = [];
        foreach ($map as $name => $value) {
            $vars["--{$name}"] = $value;
            $vars["--color-{$name}"] = $value;
        }

        return $vars;
    }

    private static function font(string $value): string
    {
        if (str_starts_with($value, 'custom:')) {
            return '"'.substr($value, 7).'", '.ThemeSchema::FONT_STACKS['sans'];
        }

        return ThemeSchema::FONT_STACKS[$value] ?? ThemeSchema::FONT_STACKS['sans'];
    }

    /**
     * @param  array<string, string>  $vars
     */
    private static function declarations(array $vars): string
    {
        $out = '';

        foreach ($vars as $name => $value) {
            // Defence in depth: values are validated upstream; never let a brace, semicolon or tag through.
            if (preg_match('/[{};<>]/', $value)) {
                continue;
            }

            $out .= "{$name}:{$value};";
        }

        return $out;
    }
}

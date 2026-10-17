<?php

namespace App\Core\Themes;

/**
 * The fixed vocabulary themes are written in (ADR-031): design settings,
 * layout variants, page section types and their fields. Themes choose
 * values; the core owns the components that render them. Every value is
 * validated here before it reaches CSS or the page, so a theme or a merchant
 * edit can never inject markup, script or arbitrary CSS.
 *
 * Field types:
 *  - color: "#rrggbb"
 *  - select: one of `options` (values are strings)
 *  - boolean
 *  - integer: within `min`/`max`
 *  - string / text: plain text up to `max` characters (never rendered as HTML)
 *  - link: "" | internal path "/…" | "https://…"
 *  - image: "" | "asset:<path in the theme's assets>" | "media:themes/<file>" or "media:library/<file>" (merchant upload)
 *  - font: a font stack key or "custom:<Family>" declared by the theme
 */
final class ThemeSchema
{
    public const CAPABILITIES = [
        'home.sections' => 'Designs the home page with sections',
        'custom_fonts' => 'Ships its own web fonts',
        'dark_mode' => 'Has dark colors',
        'demo_content' => 'Includes demo products and categories',
    ];

    public const COLORS = [
        'background' => 'Page background',
        'foreground' => 'Text',
        'primary' => 'Buttons and links',
        'primary_foreground' => 'Text on buttons',
        'muted' => 'Subtle backgrounds',
        'muted_foreground' => 'Secondary text',
        'accent' => 'Highlights',
        'accent_foreground' => 'Text on highlights',
        'border' => 'Borders',
    ];

    /** Font stacks a theme may use without shipping font files. */
    public const FONT_STACKS = [
        'sans' => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
        'system' => "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif",
        'humanist' => "Seravek, 'Gill Sans Nova', Ubuntu, Calibri, 'DejaVu Sans', source-sans-pro, sans-serif",
        'geometric' => "Avenir, Montserrat, Corbel, 'URW Gothic', source-sans-pro, sans-serif",
        'rounded' => "ui-rounded, 'Hiragino Maru Gothic ProN', Quicksand, Comfortaa, Manjari, 'Arial Rounded MT', Calibri, source-sans-pro, sans-serif",
        'serif' => "ui-serif, Charter, 'Bitstream Charter', 'Sitka Text', Cambria, Georgia, serif",
        'didone' => "Didot, 'Bodoni MT', 'Noto Serif Display', 'URW Palladio L', P052, Sylfaen, serif",
        'mono' => "ui-monospace, 'Cascadia Code', 'Source Code Pro', Menlo, Consolas, 'DejaVu Sans Mono', monospace",
    ];

    public const RADII = ['0', '0.25rem', '0.375rem', '0.5rem', '0.625rem', '0.75rem', '1rem', '1.5rem'];

    public const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    public const ASSET_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'woff2'];

    public const MAX_SECTIONS = 12;

    /** Pages whose content is a list of sections. */
    public const SECTION_PAGES = ['home'];

    /**
     * Design settings every theme has. The theme supplies defaults; the
     * merchant may override any of them.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function designFields(): array
    {
        $fields = [];

        foreach (self::COLORS as $key => $label) {
            $fields["color.{$key}"] = ['type' => 'color', 'label' => $label, 'group' => 'Colors'];
        }

        return $fields + [
            'font_body' => ['type' => 'font', 'label' => 'Body font', 'group' => 'Typography'],
            'font_heading' => ['type' => 'font', 'label' => 'Heading font', 'group' => 'Typography'],
            'radius' => ['type' => 'select', 'label' => 'Corner radius', 'group' => 'Shape and spacing', 'options' => self::RADII],
            'density' => ['type' => 'select', 'label' => 'Spacing', 'group' => 'Shape and spacing', 'options' => ['compact', 'comfortable', 'spacious']],
            'container' => ['type' => 'select', 'label' => 'Page width', 'group' => 'Shape and spacing', 'options' => ['narrow', 'default', 'wide']],
            'layout.header' => ['type' => 'select', 'label' => 'Header', 'group' => 'Layout', 'options' => ['classic', 'centered', 'minimal']],
            'layout.footer' => ['type' => 'select', 'label' => 'Footer', 'group' => 'Layout', 'options' => ['simple', 'columns']],
            'layout.product_card' => ['type' => 'select', 'label' => 'Product cards', 'group' => 'Layout', 'options' => ['standard', 'minimal', 'framed']],
            'layout.grid_columns' => ['type' => 'select', 'label' => 'Products per row', 'group' => 'Layout', 'options' => ['3', '4']],
            'layout.product_page' => ['type' => 'select', 'label' => 'Product page', 'group' => 'Layout', 'options' => ['split', 'stacked']],
            'logo' => ['type' => 'image', 'label' => 'Logo', 'group' => 'Branding'],
            'favicon' => ['type' => 'image', 'label' => 'Favicon', 'group' => 'Branding'],
            'announcement' => ['type' => 'string', 'label' => 'Announcement bar', 'group' => 'Branding', 'max' => 160],
            'footer_text' => ['type' => 'text', 'label' => 'Footer text', 'group' => 'Branding', 'max' => 500],
        ] + ThemeStyleFields::fields() + PageSettingFields::fields();
    }

    /**
     * Values used when nothing else applies (no theme installed).
     *
     * @return array<string, string>
     */
    public static function builtinDesign(): array
    {
        return [
            'color.background' => '#ffffff',
            'color.foreground' => '#171717',
            'color.primary' => '#171717',
            'color.primary_foreground' => '#fafafa',
            'color.muted' => '#f5f5f5',
            'color.muted_foreground' => '#737373',
            'color.accent' => '#f5f5f5',
            'color.accent_foreground' => '#171717',
            'color.border' => '#e5e5e5',
            'font_body' => 'sans',
            'font_heading' => 'sans',
            'radius' => '0.625rem',
            'density' => 'comfortable',
            'container' => 'default',
            'layout.header' => 'classic',
            'layout.footer' => 'simple',
            'layout.product_card' => 'standard',
            'layout.grid_columns' => '4',
            'layout.product_page' => 'split',
            'logo' => '',
            'favicon' => '',
            'announcement' => '',
            'footer_text' => '',
        ];
    }

    /**
     * @return array<string, array{label: string, fields: array<string, array<string, mixed>>}>
     */
    public static function sectionTypes(): array
    {
        $button = [
            'button_label' => ['type' => 'string', 'label' => 'Button label', 'max' => 40],
            'button_link' => ['type' => 'link', 'label' => 'Button link'],
        ];

        return [
            'hero' => ['label' => 'Hero banner', 'fields' => [
                'heading' => ['type' => 'string', 'label' => 'Heading', 'max' => 120, 'required' => true],
                'subheading' => ['type' => 'text', 'label' => 'Text', 'max' => 300],
                ...$button,
                'image' => ['type' => 'image', 'label' => 'Background image'],
                'alignment' => ['type' => 'select', 'label' => 'Alignment', 'options' => ['left', 'center'], 'default' => 'left'],
                'height' => ['type' => 'select', 'label' => 'Height', 'options' => ['small', 'medium', 'large'], 'default' => 'medium'],
            ]],
            'featured_products' => ['label' => 'Featured products', 'fields' => [
                'title' => ['type' => 'string', 'label' => 'Title', 'max' => 80, 'default' => 'New arrivals'],
                'source' => ['type' => 'select', 'label' => 'Products', 'options' => ['latest', 'featured', 'category'], 'default' => 'latest'],
                'category' => ['type' => 'string', 'label' => 'Category slug (when "category")', 'max' => 191],
                'limit' => ['type' => 'integer', 'label' => 'Number of products', 'min' => 2, 'max' => 12, 'default' => 8],
            ]],
            'category_grid' => ['label' => 'Categories', 'fields' => [
                'title' => ['type' => 'string', 'label' => 'Title', 'max' => 80, 'default' => 'Shop by category'],
                'limit' => ['type' => 'integer', 'label' => 'Number of categories', 'min' => 2, 'max' => 12, 'default' => 6],
            ]],
            'image_with_text' => ['label' => 'Image with text', 'fields' => [
                'heading' => ['type' => 'string', 'label' => 'Heading', 'max' => 120, 'required' => true],
                'body' => ['type' => 'text', 'label' => 'Text', 'max' => 1000],
                'image' => ['type' => 'image', 'label' => 'Image'],
                'image_position' => ['type' => 'select', 'label' => 'Image position', 'options' => ['left', 'right'], 'default' => 'left'],
                ...$button,
            ]],
            'rich_text' => ['label' => 'Text', 'fields' => [
                'heading' => ['type' => 'string', 'label' => 'Heading', 'max' => 120],
                'body' => ['type' => 'text', 'label' => 'Text', 'max' => 2000, 'required' => true],
                'alignment' => ['type' => 'select', 'label' => 'Alignment', 'options' => ['left', 'center'], 'default' => 'center'],
            ]],
            'promo_banner' => ['label' => 'Promotion banner', 'fields' => [
                'text' => ['type' => 'string', 'label' => 'Text', 'max' => 200, 'required' => true],
                ...$button,
                'tone' => ['type' => 'select', 'label' => 'Style', 'options' => ['primary', 'accent', 'muted'], 'default' => 'primary'],
            ]],
        ];
    }

    /**
     * Home page used when no theme supplies one.
     *
     * @return list<array{type: string, settings: array<string, mixed>}>
     */
    public static function builtinHomeSections(): array
    {
        return [
            ['type' => 'hero', 'settings' => ['heading' => 'Welcome', 'subheading' => 'Browse the latest arrivals or shop by category.', 'button_label' => 'Shop all products', 'button_link' => '/search']],
            ['type' => 'category_grid', 'settings' => []],
            ['type' => 'featured_products', 'settings' => []],
        ];
    }

    /**
     * Validates one value against a field definition.
     *
     * @param  array<string, mixed>  $field
     * @param  callable(string): bool  $assetExists  whether "asset:<path>" exists in the theme
     * @param  list<string>  $customFonts  font families the theme declares
     * @return array{0: mixed, 1: string|null} the normalised value and an error, if any
     */
    public static function check(array $field, mixed $value, callable $assetExists, array $customFonts = []): array
    {
        if ($value === null || $value === '') {
            return ($field['required'] ?? false) ? [null, 'is required'] : [$field['type'] === 'boolean' ? false : '', null];
        }

        switch ($field['type']) {
            case 'color':
                return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? [strtolower($value), null] : [null, 'must be a color like #1a2b3c'];

            case 'select':
                $value = is_scalar($value) ? (string) $value : '';

                return in_array($value, $field['options'], true) ? [$value, null] : [null, 'must be one of '.implode(', ', $field['options'])];

            case 'boolean':
                return is_bool($value) || in_array($value, [0, 1, '0', '1'], true) ? [(bool) $value, null] : [null, 'must be true or false'];

            case 'integer':
                if (! is_int($value) && ! (is_string($value) && preg_match('/^-?\d+$/', $value))) {
                    return [null, 'must be a whole number'];
                }

                $value = (int) $value;

                return $value < ($field['min'] ?? PHP_INT_MIN) || $value > ($field['max'] ?? PHP_INT_MAX)
                    ? [null, "must be between {$field['min']} and {$field['max']}"]
                    : [$value, null];

            case 'string':
            case 'text':
                $max = (int) ($field['max'] ?? 255);
                if (! is_string($value) || mb_strlen($value) > $max) {
                    return [null, "must be text of at most {$max} characters"];
                }
                // Plain text only; control characters other than newlines are refused.
                if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) || ($field['type'] === 'string' && str_contains($value, "\n"))) {
                    return [null, 'contains characters that are not allowed'];
                }

                return [trim($value), null];

            case 'link':
                return is_string($value) && self::isSafeLink($value) ? [$value, null] : [null, 'must be a path starting with / or an https:// address'];

            case 'image':
                if (! is_string($value)) {
                    return [null, 'is not a valid image'];
                }
                if (str_starts_with($value, 'media:')) {
                    return preg_match('#^media:(themes|library)/[A-Za-z0-9]{40}\.(png|jpg|webp)$#', $value) ? [$value, null] : [null, 'is not a valid uploaded image'];
                }
                if (str_starts_with($value, 'asset:')) {
                    $path = substr($value, 6);
                    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

                    return in_array($ext, self::IMAGE_EXTENSIONS, true) && $assetExists($path) ? [$value, null] : [null, 'must be a PNG, JPEG or WebP file in the theme\'s assets'];
                }

                return [null, 'is not a valid image'];

            case 'font':
                if (is_string($value) && array_key_exists($value, self::FONT_STACKS)) {
                    return [$value, null];
                }

                return is_string($value) && str_starts_with($value, 'custom:') && in_array(substr($value, 7), $customFonts, true)
                    ? [$value, null]
                    : [null, 'must be a supported font'];
        }

        return [null, 'has an unknown type'];
    }

    public static function isSafeLink(string $link): bool
    {
        if (strlen($link) > 500) {
            return false;
        }

        // Internal path: one leading slash (not "//host"), no backslashes or control characters.
        if (preg_match('#^/(?![/\\\\])[A-Za-z0-9\-._~/?=&%\#+]*$#', $link)) {
            return true;
        }

        return str_starts_with($link, 'https://')
            && filter_var($link, FILTER_VALIDATE_URL) !== false
            && ! preg_match('/[\s<>"\'`]/', $link);
    }

    /**
     * Validates a list of sections for a page.
     *
     * @param  callable(string): bool  $assetExists
     * @return array{0: list<array{type: string, settings: array<string, mixed>}>, 1: array<string, string>} sections and errors keyed "index.field"
     */
    public static function checkSections(mixed $sections, callable $assetExists): array
    {
        if (! is_array($sections) || ! array_is_list($sections)) {
            return [[], ['sections' => 'must be a list']];
        }

        if (count($sections) > self::MAX_SECTIONS) {
            return [[], ['sections' => 'may contain at most '.self::MAX_SECTIONS.' sections']];
        }

        $types = self::sectionTypes();
        $clean = [];
        $errors = [];

        foreach ($sections as $i => $section) {
            $type = is_array($section) ? ($section['type'] ?? null) : null;

            if (! is_string($type) || ! isset($types[$type])) {
                $errors["{$i}.type"] = 'is not a known section type';

                continue;
            }

            $settings = is_array($section['settings'] ?? null) ? $section['settings'] : [];
            $unknown = array_diff(array_keys($settings), array_keys($types[$type]['fields']));

            if ($unknown !== []) {
                $errors["{$i}.settings"] = 'has unknown settings: '.implode(', ', $unknown);
            }

            $values = [];
            foreach ($types[$type]['fields'] as $key => $field) {
                [$value, $error] = self::check($field, $settings[$key] ?? ($field['default'] ?? null), $assetExists);

                if ($error !== null) {
                    $errors["{$i}.{$key}"] = "{$field['label']} {$error}";
                }

                $values[$key] = $value;
            }

            $clean[] = ['type' => $type, 'settings' => $values];
        }

        return [$clean, $errors];
    }
}

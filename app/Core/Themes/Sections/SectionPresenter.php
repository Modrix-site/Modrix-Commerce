<?php

namespace App\Core\Themes\Sections;

use App\Core\Localization\ContentTranslations;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\ResolvedTheme;
use App\Core\Themes\StorefrontLinks;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\StorefrontCatalog;

/**
 * Turns a template or section group into what the storefront renders:
 * visible sections in order, each with its blocks, and setting values
 * resolved for display. The schema decides how each value is resolved:
 *
 * - image: the image URL (null when missing)
 * - url: the link, or "" when it points to a page that no longer exists
 * - collection: {handle, title, url, image_url, products} where "products"
 *   holds up to the setting's "products" count (default 0); an empty value,
 *   or a category that no longer exists, uses the setting's "fallback"
 *   ("latest" or "featured") products
 * - product: the product card, or null
 * - text / textarea / richtext: translated into the shopper's language
 */
final class SectionPresenter
{
    /**
     * Collections already resolved on this page: sections that show the same
     * category (or the same fallback) with the same count share one lookup.
     *
     * @var array<string, list<array<string, mixed>>>
     */
    private array $products = [];

    /** @var array<string, Category|null> categories by handle, looked up once per page */
    private array $categories = [];

    public function __construct(
        private readonly ThemeTemplates $templates,
        private readonly StorefrontCatalog $catalog,
        private readonly StorefrontLinks $links,
        private readonly ContentTranslations $translations,
        private readonly StoreSettings $store,
    ) {}

    /**
     * Header and footer groups plus the page template, or null for themes without sections.
     *
     * @param  string|null  $template  null on pages without a template (checkout, account): header and footer only
     * @return array{template: string|null, header: list<array<string, mixed>>, footer: list<array<string, mixed>>, main: list<array<string, mixed>>}|null
     */
    public function page(ResolvedTheme $theme, ?string $template): ?array
    {
        if (! ThemeTemplates::supports($theme->manifest)) {
            return null;
        }

        return [
            'template' => $template,
            'header' => $this->present($theme, 'group:header'),
            'footer' => $this->present($theme, 'group:footer'),
            'main' => $template === null ? [] : $this->present($theme, "template:{$template}"),
        ];
    }

    /**
     * @return list<array{id: string, type: string, settings: array<string, mixed>, blocks: list<array{id: string, type: string, settings: array<string, mixed>}>}>
     */
    public function present(ResolvedTheme $theme, string $key): array
    {
        $schemas = $this->templates->schemas($theme->manifest);
        $data = $this->templates->get($theme->manifest, $key);
        $sections = [];

        foreach ($data['order'] as $id) {
            $section = $data['sections'][$id] ?? null;
            $schema = $schemas[$section['type'] ?? ''] ?? null;

            if ($section === null || $schema === null || ($section['disabled'] ?? false)) {
                continue;
            }

            $blockSchemas = collect((array) ($schema['blocks'] ?? []))->keyBy('type');
            $blocks = [];

            foreach ($section['block_order'] as $blockId) {
                $block = $section['blocks'][$blockId] ?? null;

                if ($block === null || ($block['disabled'] ?? false) || ! $blockSchemas->has($block['type'])) {
                    continue;
                }

                $blocks[] = [
                    'id' => $blockId,
                    'type' => $block['type'],
                    'settings' => $this->resolve($theme, (array) ($blockSchemas[$block['type']]['settings'] ?? []), $block['settings']),
                ];
            }

            $sections[] = [
                'id' => $id,
                'type' => $section['type'],
                'settings' => $this->resolve($theme, (array) ($schema['settings'] ?? []), $section['settings']),
                'blocks' => $blocks,
            ];
        }

        return $sections;
    }

    /**
     * @param  list<array<string, mixed>>  $definitions
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function resolve(ResolvedTheme $theme, array $definitions, array $values): array
    {
        $resolved = [];

        foreach (SectionSettings::byId($definitions) as $id => $definition) {
            $value = $values[$id] ?? SectionSettings::default($definition);

            $resolved[$id] = match ($definition['type']) {
                'image' => $theme->imageUrl($value),
                'url' => is_string($value) && $value !== '' ? ($this->links->resolve($value) ?? '') : '',
                'collection' => $this->collection($definition, is_string($value) ? $value : ''),
                'product' => $this->product(is_string($value) ? $value : ''),
                'text', 'textarea', 'richtext' => is_string($value) && $value !== '' && ! $this->translations->skipKey($id) ? $this->translations->translate($value) : $value,
                default => $value,
            };
        }

        return $resolved;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>|null
     */
    private function collection(array $definition, string $handle): ?array
    {
        $count = max(0, min(24, (int) ($definition['products'] ?? 0)));
        $currency = $this->store->currency();

        $category = $handle === '' ? null : $this->category($handle);

        // No category chosen, or the chosen one is gone (deleted, hidden, or a
        // theme's demo category that was never imported): use the fallback.
        if ($category === null) {
            $fallback = $definition['fallback'] ?? null;

            return match ($fallback) {
                'latest', 'featured' => ['handle' => '', 'title' => '', 'url' => '/search', 'image_url' => null, 'products' => $this->products("{$fallback}|{$count}|{$currency}", fn () => $count === 0 ? [] : ($fallback === 'latest'
                    ? $this->catalog->latest($currency, $count)
                    : $this->catalog->featured($currency, $count)))],
                default => null,
            };
        }

        return [
            'handle' => $category->slug,
            'title' => $category->localized('name'),
            'url' => "/c/{$category->slug}",
            'image_url' => $category->imageUrl(),
            'products' => $this->products("category:{$category->id}|{$count}|{$currency}", fn () => $count === 0 ? [] : array_values($this->catalog->listing($currency, $category, null, 'newest', $count)->items())),
        ];
    }

    private function category(string $handle): ?Category
    {
        if (! array_key_exists($handle, $this->categories)) {
            $this->categories[$handle] = Category::query()->active()->withCurrentTranslation()->where('slug', $handle)->first();
        }

        return $this->categories[$handle];
    }

    /**
     * Product cards for a collection, loaded once per page for the same
     * category (or fallback), count and currency.
     *
     * @param  callable(): list<array<string, mixed>>  $load
     * @return list<array<string, mixed>>
     */
    private function products(string $key, callable $load): array
    {
        return $this->products[$key] ??= $load();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function product(string $handle): ?array
    {
        $product = $handle === '' ? null : $this->catalog->findVisible($handle);

        return $product === null ? null : $this->catalog->card($product, $this->store->currency());
    }
}

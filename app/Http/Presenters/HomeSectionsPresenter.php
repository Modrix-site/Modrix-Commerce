<?php

namespace App\Http\Presenters;

use App\Core\Localization\ContentTranslations;
use App\Core\Themes\ResolvedTheme;
use App\Core\Themes\StorefrontLinks;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\StorefrontCatalog;

/**
 * Turns the theme's home sections into render-ready props: settings as
 * validated plain values, image references resolved to URLs, and catalog
 * data loaded by the core (themes never query anything themselves).
 */
final class HomeSectionsPresenter
{
    public function __construct(private readonly StorefrontCatalog $catalog) {}

    /**
     * @return list<array{key: string, type: string, settings: array<string, mixed>, data: array<string, mixed>}>
     */
    public function present(ResolvedTheme $theme, string $currency): array
    {
        $sections = [];

        foreach ($theme->sections('home') as $i => $section) {
            $settings = $this->translate($section['settings']);
            $settings = app(StorefrontLinks::class)->content($settings);
            $data = match ($section['type']) {
                'hero', 'image_with_text', 'campaign' => ['image' => $theme->imageUrl($settings['image'] ?? null)],
                'promotion_grid' => ['tiles' => array_values(array_filter(array_map(fn (int $i) => [
                    'title' => $settings["tile_{$i}_title"] ?? '', 'text' => $settings["tile_{$i}_text"] ?? '',
                    'image' => $theme->imageUrl($settings["tile_{$i}_image"] ?? null), 'link' => $settings["tile_{$i}_link"] ?? '',
                ], range(1, 8)), fn (array $tile) => ($tile['title'] !== '' || $tile['image'] !== null) && ($tile['link'] === '' || app(StorefrontLinks::class)->resolve($tile['link']) !== null)))],
                'featured_products' => ['products' => $this->products($settings, $currency)],
                'category_grid' => ['categories' => Category::query()->active()->withCurrentTranslation()->whereNull('parent_id')
                    ->orderBy('position')->orderBy('name')->limit((int) ($settings['limit'] ?? 6))
                    ->get(['id', 'name', 'slug', 'description', 'image_disk', 'image_path'])
                    ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->localized('name'), 'slug' => $c->slug, 'description' => $c->localized('description'), 'image_url' => $c->imageUrl()])
                    ->all()],
                default => [],
            };

            unset($settings['image']);
            $sections[] = ['key' => "{$i}-{$section['type']}", 'type' => $section['type'], 'settings' => $settings, 'data' => $data];
        }

        return $sections;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return list<array<string, mixed>>
     */
    private function products(array $settings, string $currency): array
    {
        $limit = (int) ($settings['limit'] ?? 8);

        if (($settings['source'] ?? 'latest') === 'featured') {
            return $this->catalog->featured($currency, $limit);
        }

        if (($settings['source'] ?? 'latest') === 'category' && ($settings['category'] ?? '') !== '') {
            $category = Category::query()->active()->where('slug', $settings['category'])->first();

            if ($category !== null) {
                /** @var list<array<string, mixed>> */
                return array_values($this->catalog->listing($currency, $category, null, 'newest', $limit)->items());
            }
        }

        return $this->catalog->latest($currency, $limit);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function translate(array $settings): array
    {
        $translations = app(ContentTranslations::class);
        foreach ($settings as $key => $value) {
            if (is_string($key) && is_string($value) && ! $translations->skipKey($key)) {
                $settings[$key] = $translations->translate($value);
            }
        }

        return $settings;
    }
}

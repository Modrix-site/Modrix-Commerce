<?php

namespace App\Http\Presenters;

use App\Core\Themes\ResolvedTheme;
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
            $settings = $section['settings'];
            $data = match ($section['type']) {
                'hero', 'image_with_text' => ['image' => $theme->imageUrl($settings['image'] ?? null)],
                'featured_products' => ['products' => $this->products($settings, $currency)],
                'category_grid' => ['categories' => Category::query()->active()->withCurrentTranslation()->whereNull('parent_id')
                    ->orderBy('position')->orderBy('name')->limit((int) ($settings['limit'] ?? 6))
                    ->get(['id', 'name', 'slug', 'description'])
                    ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->localized('name'), 'slug' => $c->slug, 'description' => $c->localized('description')])
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
}

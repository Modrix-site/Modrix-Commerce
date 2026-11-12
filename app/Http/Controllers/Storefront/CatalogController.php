<?php

namespace App\Http\Controllers\Storefront;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\PageSettingFields;
use App\Core\Themes\Sections\ThemeTemplates;
use App\Core\Themes\ThemeResolver;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\ProductAttribute;
use App\Domains\Catalog\Models\ProductTag;
use App\Domains\Catalog\StorefrontCatalog;
use App\Http\Controllers\Controller;
use App\Http\Presenters\HomeSectionsPresenter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogController extends Controller
{
    /**
     * The home page is the active theme's list of sections, followed by module slots.
     */
    public function home(Request $request, StoreSettings $store, StorefrontSlots $slots, ThemeResolver $themes, HomeSectionsPresenter $sections): Response
    {
        $theme = $themes->forRequest($request);

        return Inertia::render('storefront/home', [
            // Themes built from sections render the index template (shared as
            // "themeSections"); the legacy home sections would go unused.
            'sections' => ThemeTemplates::supports($theme->manifest) ? [] : $sections->present($theme, $store->currency()),
            'slots' => $slots->render('home.after_featured', []),
        ]);
    }

    public function category(Request $request, string $slug, StorefrontCatalog $catalog, StoreSettings $store, StorefrontSlots $slots): Response
    {
        $category = Category::query()->active()->withCurrentTranslation()->where('slug', $slug)->firstOrFail();
        $sort = $this->sort($request);

        return Inertia::render('storefront/listing', [
            'title' => $category->localized('name'),
            'description' => $category->localized('description'),
            'image' => $category->imageUrl(),
            'meta' => [
                'title' => $category->localized('meta_title') ?: $category->localized('name'),
                'description' => $category->localized('meta_description'),
            ],
            'breadcrumbs' => collect($category->ancestors())
                ->map(fn (Category $c) => ['name' => $c->localized('name'), 'slug' => $c->slug])
                ->push(['name' => $category->localized('name'), 'slug' => $category->slug])
                ->values(),
            'subcategories' => $category->children()->active()->withCurrentTranslation()->get(['id', 'name', 'slug', 'image_disk', 'image_path'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->localized('name'), 'slug' => $c->slug, 'image_url' => $c->imageUrl()]),
            ...$this->filterProps($request),
            'placeSlots' => $slots->renderMany(['catalog.before_products'], ['category' => $category, 'brand' => null, 'query' => null]),
            'products' => $catalog->listing($store->currency(), $category, null, $sort, perPage: $this->pageSettings($request)['per_page'], filters: $this->filters($request)),
            'sort' => $sort,
            'query' => null,
            'slots' => $slots->render('catalog.after_products', ['category' => $category, 'brand' => null, 'query' => null]),
        ]);
    }

    public function brand(Request $request, string $slug, StorefrontCatalog $catalog, StoreSettings $store, StorefrontSlots $slots): Response
    {
        $brand = Brand::query()->with('logo')->where('slug', $slug)->firstOrFail();
        $sort = $this->sort($request);

        return Inertia::render('storefront/listing', [
            'title' => $brand->name,
            'description' => $brand->description,
            'image' => $brand->logo?->url(),
            'meta' => ['title' => $brand->name, 'description' => $brand->description],
            'breadcrumbs' => [],
            'subcategories' => [],
            ...$this->filterProps($request),
            'placeSlots' => $slots->renderMany(['catalog.before_products'], ['category' => null, 'brand' => $brand, 'query' => null]),
            'products' => $catalog->listing($store->currency(), null, null, $sort, perPage: $this->pageSettings($request)['per_page'], brand: $brand, filters: $this->filters($request)),
            'sort' => $sort,
            'query' => null,
            'slots' => $slots->render('catalog.after_products', ['category' => null, 'brand' => $brand, 'query' => null]),
        ]);
    }

    public function search(Request $request, StorefrontCatalog $catalog, StoreSettings $store, StorefrontSlots $slots): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = trim((string) ($data['q'] ?? ''));
        $sort = $this->sort($request);
        $tag = ProductTag::normalise((string) ($this->filters($request)['tag'] ?? ''));
        // "/search?tag=sleeping" is the tag page themes link to.
        $title = match (true) {
            $query !== '' => st('Search results for “:query”', ['query' => $query]),
            $tag !== '' => StorefrontCatalog::tag($tag)['label'],
            default => st('All products'),
        };

        return Inertia::render('storefront/listing', [
            'title' => $title,
            'description' => null,
            'meta' => ['title' => $query === '' ? $title : st('Search: :query', ['query' => $query]), 'description' => null],
            'tag' => $tag === '' ? null : StorefrontCatalog::tag($tag),
            'breadcrumbs' => [],
            'subcategories' => [],
            ...$this->filterProps($request),
            'placeSlots' => $slots->renderMany(['catalog.before_products'], ['category' => null, 'brand' => null, 'query' => $query]),
            'products' => $catalog->listing($store->currency(), null, $query, $sort, perPage: $this->pageSettings($request)['per_page'], filters: $this->filters($request)),
            'sort' => $sort,
            'query' => $query,
            'slots' => $slots->render('catalog.after_products', ['category' => null, 'brand' => null, 'query' => $query]),
        ]);
    }

    private function sort(Request $request): string
    {
        $data = $request->validate(['sort' => ['nullable', Rule::in(StorefrontCatalog::SORTS)]]);

        return $data['sort'] ?? $this->pageSettings($request)['default_sort'];
    }

    /**
     * The merchant's Catalog page settings (customizer).
     *
     * @return array<string, mixed>
     */
    private function pageSettings(Request $request): array
    {
        return PageSettingFields::resolve(app(ThemeResolver::class)->forRequest($request)->design)['catalog'];
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'tag' => ['nullable', 'string', 'max:'.ProductTag::MAX_LENGTH],
            'in_stock' => ['nullable', 'boolean'],
            'on_sale' => ['nullable', 'boolean'],
            'min_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'options' => ['sometimes', 'array', 'max:30'], 'options.*' => ['integer', 'distinct', 'exists:product_attribute_values,id'],
        ]);
    }

    /** @return array<string, mixed> */
    private function filterProps(Request $request): array
    {
        return [
            'filters' => $this->filters($request),
            'filterBrands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            // Tags on visible products, most used first (name, label, slug, url, count).
            'filterTags' => app(StorefrontCatalog::class)->tags(50),
            'filterOptions' => ProductAttribute::query()->with('values')->orderBy('position')->get()->map(fn (ProductAttribute $attribute) => [
                'id' => $attribute->id, 'name' => $attribute->name,
                'values' => $attribute->values->map(fn ($value) => ['id' => $value->id, 'value' => $value->value]),
            ]),
        ];
    }
}

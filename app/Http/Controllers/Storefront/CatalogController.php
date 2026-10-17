<?php

namespace App\Http\Controllers\Storefront;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\PageSettingFields;
use App\Core\Themes\ThemeResolver;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
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
        return Inertia::render('storefront/home', [
            'sections' => $sections->present($themes->forRequest($request), $store->currency()),
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
            'meta' => [
                'title' => $category->localized('meta_title') ?: $category->localized('name'),
                'description' => $category->localized('meta_description'),
            ],
            'breadcrumbs' => collect($category->ancestors())
                ->map(fn (Category $c) => ['name' => $c->localized('name'), 'slug' => $c->slug])
                ->push(['name' => $category->localized('name'), 'slug' => $category->slug])
                ->values(),
            'subcategories' => $category->children()->active()->withCurrentTranslation()->get(['id', 'name', 'slug'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->localized('name'), 'slug' => $c->slug]),
            'placeSlots' => $slots->renderMany(['catalog.before_products'], ['category' => $category, 'brand' => null, 'query' => null]),
            'products' => $catalog->listing($store->currency(), $category, null, $sort, perPage: $this->pageSettings($request)['per_page']),
            'sort' => $sort,
            'query' => null,
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
            'placeSlots' => $slots->renderMany(['catalog.before_products'], ['category' => null, 'brand' => $brand, 'query' => null]),
            'products' => $catalog->listing($store->currency(), null, null, $sort, perPage: $this->pageSettings($request)['per_page'], brand: $brand),
            'sort' => $sort,
            'query' => null,
        ]);
    }

    public function search(Request $request, StorefrontCatalog $catalog, StoreSettings $store, StorefrontSlots $slots): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = trim((string) ($data['q'] ?? ''));
        $sort = $this->sort($request);

        return Inertia::render('storefront/listing', [
            'title' => $query === '' ? st('All products') : st('Search results for “:query”', ['query' => $query]),
            'description' => null,
            'meta' => ['title' => $query === '' ? st('All products') : st('Search: :query', ['query' => $query]), 'description' => null],
            'breadcrumbs' => [],
            'subcategories' => [],
            'placeSlots' => $slots->renderMany(['catalog.before_products'], ['category' => null, 'brand' => null, 'query' => $query]),
            'products' => $catalog->listing($store->currency(), null, $query, $sort, perPage: $this->pageSettings($request)['per_page']),
            'sort' => $sort,
            'query' => $query,
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
}

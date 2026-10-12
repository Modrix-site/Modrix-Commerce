<?php

namespace App\Http\Controllers\Storefront;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\ThemeResolver;
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

    public function category(Request $request, string $slug, StorefrontCatalog $catalog, StoreSettings $store): Response
    {
        $category = Category::query()->active()->where('slug', $slug)->firstOrFail();
        $sort = $this->sort($request);

        return Inertia::render('storefront/listing', [
            'title' => $category->name,
            'description' => $category->description,
            'meta' => [
                'title' => $category->meta_title ?: $category->name,
                'description' => $category->meta_description,
            ],
            'breadcrumbs' => collect($category->ancestors())
                ->map(fn (Category $c) => ['name' => $c->name, 'slug' => $c->slug])
                ->push(['name' => $category->name, 'slug' => $category->slug])
                ->values(),
            'subcategories' => $category->children()->active()->get(['id', 'name', 'slug']),
            'products' => $catalog->listing($store->currency(), $category, null, $sort),
            'sort' => $sort,
            'query' => null,
        ]);
    }

    public function search(Request $request, StorefrontCatalog $catalog, StoreSettings $store): Response
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $query = trim((string) ($data['q'] ?? ''));
        $sort = $this->sort($request);

        return Inertia::render('storefront/listing', [
            'title' => $query === '' ? 'All products' : "Search results for “{$query}”",
            'description' => null,
            'meta' => ['title' => $query === '' ? 'All products' : "Search: {$query}", 'description' => null],
            'breadcrumbs' => [],
            'subcategories' => [],
            'products' => $catalog->listing($store->currency(), null, $query, $sort),
            'sort' => $sort,
            'query' => $query,
        ]);
    }

    private function sort(Request $request): string
    {
        $data = $request->validate(['sort' => ['nullable', Rule::in(StorefrontCatalog::SORTS)]]);

        return $data['sort'] ?? 'newest';
    }
}

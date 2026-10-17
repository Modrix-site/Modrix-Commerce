<?php

namespace App\Http\Middleware;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Localization\Locales;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\ResolvedTheme;
use App\Core\Themes\ThemeResolver;
use App\Domains\Cart\CartManager;
use App\Domains\Catalog\CategoryMenu;
use App\Domains\Catalog\Models\Category;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the storefront chrome (store name, top-level categories, cart size,
 * the theme to render with and any staff preview) with storefront pages only.
 */
class ShareStorefrontData
{
    /** Storefront pages that show product cards (catalog, product, cart). */
    private const PRODUCT_CARD_ROUTES = ['home', 'storefront.category', 'storefront.brand', 'storefront.search', 'storefront.product', 'cart.show'];

    public function __construct(
        private readonly Locales $locales,
        private readonly StoreSettings $store,
        private readonly CartManager $carts,
        private readonly ThemeResolver $themes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('storefront', fn () => [
            'locale' => app()->getLocale(),
            'locales' => Locales::options($this->locales->enabled()),
            'storeName' => $this->store->name(),
            'currency' => $this->store->currency(),
            'categories' => Category::query()
                ->active()
                ->withCurrentTranslation()
                ->whereNull('parent_id')
                ->orderBy('position')
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'name', 'slug'])
                ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->localized('name'), 'slug' => $c->slug]),
            // Full category tree (with subcategories) for menus.
            'categoryTree' => fn () => app(CategoryMenu::class)->tree(),
            'cartCount' => $this->cartCount($request),
            'theme' => $this->themes->forRequest($request)->toProps(),
            // Extension blocks in the header, and on every product card (once per page)
            // on the pages that show product cards.
            'placeSlots' => fn () => app(StorefrontSlots::class)->renderMany(
                $request->routeIs(...self::PRODUCT_CARD_ROUTES) ? ['header.actions', 'product.card'] : ['header.actions'],
                [],
            ),
            'themePreview' => $this->preview($request),
        ]);

        return $next($request);
    }

    /**
     * @return array{name: string, displayName: string, exitUrl: string, manageUrl: string}|null
     */
    private function preview(Request $request): ?array
    {
        $theme = $this->themes->forRequest($request);

        if ($theme->source !== ResolvedTheme::SOURCE_PREVIEW) {
            return null;
        }

        return [
            'name' => $theme->manifest->name,
            'displayName' => $theme->manifest->displayName,
            'exitUrl' => route('admin.themes.preview.exit'),
            'manageUrl' => route('admin.themes.index'),
        ];
    }

    private function cartCount(Request $request): int
    {
        $user = $request->user();
        $cart = $this->carts->current($request->session(), $user instanceof User ? $user : null);

        return $cart === null ? 0 : (int) $cart->items()->sum('quantity');
    }
}

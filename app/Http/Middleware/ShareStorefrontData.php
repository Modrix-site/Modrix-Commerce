<?php

namespace App\Http\Middleware;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Localization\Locales;
use App\Core\Settings\Currencies;
use App\Core\Settings\GeneralSettings;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StorefrontCurrencies;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\ResolvedTheme;
use App\Core\Themes\StoreNavigation;
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
        private readonly StorefrontSlots $slots,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $currencyService = app(StorefrontCurrencies::class);
        $rates = $currencyService->rates();
        $requested = $request->query('currency', $request->session()->get('storefront.currency', $this->store->currency()));
        $selected = is_string($requested) && isset($rates[$requested]) ? $requested : $this->store->currency();
        $request->session()->put('storefront.currency', $selected);
        $converting = $request->routeIs('home', 'storefront.search', 'storefront.category', 'storefront.brand', 'storefront.product', 'cart.show');
        $displayCurrency = $converting ? $selected : $this->store->currency();
        if ($converting) {
            $request->attributes->set('storefront.display_currency', $displayCurrency);
        } else {
            $request->attributes->remove('storefront.display_currency');
        }
        Inertia::share('storefront', fn () => [
            'locale' => app()->getLocale(),
            'direction' => Locales::direction(app()->getLocale()),
            'displayCurrency' => $displayCurrency,
            'canSwitchCurrency' => $converting,
            'baseCurrency' => $this->store->currency(),
            'currencies' => array_map(fn ($code) => ['code' => $code, 'name' => Currencies::ALL[$code]['name']], array_keys($rates)),
            'currencyNotice' => count($rates) > 1 ? 'Converted prices are estimates. Checkout and payments use '.$this->store->currency().'.' : null,
            'locales' => Locales::options($this->locales->enabled()),
            'storeName' => $this->store->name(),
            'seoTitle' => app(SettingsRepository::class)->get('store.meta_title'),
            'seoDescription' => app(SettingsRepository::class)->get('store.meta_description'),
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
            // Settings → General → Social links, for themes.
            'social' => fn () => array_filter(app(GeneralSettings::class)->social()),
            'theme' => $this->themes->forRequest($request)->toProps(),
            // Extension blocks in the header, and on every product card (once per page)
            // on the pages that show product cards.
            'placeSlots' => fn () => app(StorefrontSlots::class)->renderMany(
                $request->routeIs(...self::PRODUCT_CARD_ROUTES) ? ['header.actions', 'product.card'] : ['header.actions'],
                [],
            ),
            'navigation' => app(StoreNavigation::class)->forTheme($this->themes->forRequest($request)),
            'themePreview' => $this->preview($request),
            // Global overlays (marketing popups, cookie bars) on every storefront page.
            'overlays' => $this->slots->render('storefront.overlay', []),
            'footerSlots' => $this->slots->render('storefront.footer', []),
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

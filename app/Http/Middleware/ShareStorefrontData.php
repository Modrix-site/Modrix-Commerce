<?php

namespace App\Http\Middleware;

use App\Core\Extensions\StorefrontSlots;
use App\Core\Localization\Locales;
use App\Core\Settings\Currencies;
use App\Core\Settings\GeneralSettings;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StorefrontCurrencies;
use App\Core\Settings\StoreSettings;
use App\Core\Storefront\AccountMenu;
use App\Core\Themes\ResolvedTheme;
use App\Core\Themes\Sections\SectionPresenter;
use App\Core\Themes\Sections\ThemeTemplates;
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

    /** Storefront route => the theme template it renders (sections v2 themes). */
    private const TEMPLATES = [
        'home' => 'index',
        'storefront.product' => 'product',
        'storefront.category' => 'collection',
        'storefront.brand' => 'collection',
        'storefront.search' => 'search',
        'cart.show' => 'cart',
        'storefront.page' => 'page',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // Theme editor preview: a staff member's unsaved changes, framed by the editor only.
        $user = $request->user();
        $editor = $request->query('_editor') === '1' && $user instanceof User && $user->can('themes.manage');

        app(ThemeTemplates::class)->useDraft($editor ? $user->id : null);

        if ($editor) {
            $request->attributes->set('theme.editor', true);
        }

        $currencyService = app(StorefrontCurrencies::class);
        $rates = $currencyService->rates();
        $requested = $request->query('currency', $request->session()->get('storefront.currency', $this->store->currency()));
        $selected = is_string($requested) && isset($rates[$requested]) ? $requested : $this->store->currency();
        $request->session()->put('storefront.currency', $selected);
        $converting = $request->routeIs('home', 'storefront.search', 'storefront.category', 'storefront.brand', 'storefront.product', 'cart.show', 'checkout.show');
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
            'accountLinks' => fn () => $request->user() instanceof User
                ? app(AccountMenu::class)->links()
                : [],
            'themePreview' => $editor ? null : $this->preview($request),
            // Sections of the header, footer and this page (themes built from sections).
            'themeSections' => fn () => app(SectionPresenter::class)->page($this->themes->forRequest($request), $this->template($request)),
            'themeEditor' => $editor,
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

    private function template(Request $request): ?string
    {
        foreach (self::TEMPLATES as $route => $template) {
            if ($request->routeIs($route)) {
                return $template;
            }
        }

        return null;
    }

    private function cartCount(Request $request): int
    {
        $user = $request->user();
        $cart = $this->carts->current($request->session(), $user instanceof User ? $user : null);

        return $cart === null ? 0 : (int) $cart->items()->sum('quantity');
    }
}

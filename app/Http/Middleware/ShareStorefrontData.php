<?php

namespace App\Http\Middleware;

use App\Core\Localization\Locales;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\ResolvedTheme;
use App\Core\Themes\ThemeResolver;
use App\Domains\Cart\CartManager;
use App\Domains\Catalog\Models\Category;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the storefront chrome (store name, top-level categories, cart size,
 * the theme to render with and any staff preview) with storefront pages only.
 */
class ShareStorefrontData
{
    public const LOCALE_COOKIE = 'storefront_locale';

    public function __construct(
        private readonly Locales $locales,
        private readonly StoreSettings $store,
        private readonly CartManager $carts,
        private readonly ThemeResolver $themes,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

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
            'cartCount' => $this->cartCount($request),
            'theme' => $this->themes->forRequest($request)->toProps(),
            'themePreview' => $this->preview($request),
        ]);

        return $next($request);
    }

    /**
     * `?lang=xx` (remembered in a cookie), then the cookie, then the browser's
     * Accept-Language, then the store default. Only enabled languages count.
     */
    private function resolveLocale(Request $request): string
    {
        $enabled = $this->locales->enabled();
        $requested = $request->query('lang');

        if (is_string($requested) && in_array($requested, $enabled, true)) {
            Cookie::queue(self::LOCALE_COOKIE, $requested, 60 * 24 * 365, null, null, null, true, false, 'lax');

            return $requested;
        }

        $cookie = $request->cookie(self::LOCALE_COOKIE);
        if (is_string($cookie) && in_array($cookie, $enabled, true)) {
            return $cookie;
        }

        if (count($enabled) > 1) {
            $preferred = $request->getPreferredLanguage($enabled);
            if (is_string($preferred) && in_array($preferred, $enabled, true)) {
                return $preferred;
            }
        }

        return $this->locales->default();
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

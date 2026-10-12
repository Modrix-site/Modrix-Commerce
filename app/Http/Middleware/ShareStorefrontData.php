<?php

namespace App\Http\Middleware;

use App\Core\Settings\StoreSettings;
use App\Domains\Cart\CartManager;
use App\Domains\Catalog\Models\Category;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shares the storefront chrome (store name, top-level categories, cart size)
 * with storefront pages only.
 */
class ShareStorefrontData
{
    public function __construct(
        private readonly StoreSettings $store,
        private readonly CartManager $carts,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('storefront', fn () => [
            'storeName' => $this->store->name(),
            'currency' => $this->store->currency(),
            'categories' => Category::query()
                ->active()
                ->whereNull('parent_id')
                ->orderBy('position')
                ->orderBy('name')
                ->limit(12)
                ->get(['id', 'name', 'slug']),
            'cartCount' => $this->cartCount($request),
        ]);

        return $next($request);
    }

    private function cartCount(Request $request): int
    {
        $user = $request->user();
        $cart = $this->carts->current($request->session(), $user instanceof User ? $user : null);

        return $cart === null ? 0 : (int) $cart->items()->sum('quantity');
    }
}

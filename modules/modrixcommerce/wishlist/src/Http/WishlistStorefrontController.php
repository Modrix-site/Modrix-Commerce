<?php

namespace EcoModular\Wishlist\Http;

use App\Core\Settings\StoreSettings;
use App\Domains\Cart\CartManager;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use EcoModular\Wishlist\Models\Wishlist;
use EcoModular\Wishlist\WishlistManager;
use EcoModular\Wishlist\WishlistSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Storefront controller for customer & guest wishlists.
 */
class WishlistStorefrontController extends Controller
{
    public function index(
        Request $request,
        WishlistManager $manager,
        WishlistSettings $settings,
        StoreSettings $store,
    ): Response|RedirectResponse {
        if (! $settings->enabled()) {
            abort(404, 'Wishlist is currently disabled.');
        }

        if ($settings->requireLogin() && $request->user() === null) {
            return redirect()->route('login')->with('info', __('Please sign in to view your saved items.'));
        }

        $wishlist = $manager->current($request->session(), $request->user(), create: true);
        $currency = $store->currency();

        $items = $this->formatWishlistItems($wishlist, $currency);

        return Inertia::render('module/modrixcommerce/wishlist/storefront/index', [
            'wishlist' => [
                'id' => $wishlist->id,
                'name' => $wishlist->name,
                'share_url' => $settings->allowSharing() ? route('modrixcommerce.wishlist.share', $wishlist->share_token) : null,
                'items_count' => count($items),
            ],
            'items' => $items,
            'settings' => $settings->toArray(),
        ]);
    }

    public function toggle(
        Request $request,
        WishlistManager $manager,
        WishlistSettings $settings,
    ): RedirectResponse {
        if (! $settings->enabled()) {
            abort(404);
        }

        if ($settings->requireLogin() && $request->user() === null) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('Please sign in to save items to your wishlist.'),
            ]);

            return redirect()->route('login');
        }

        $data = $request->validate([
            'product_id' => ['required', 'integer'],
            'variant_id' => ['nullable', 'integer'],
        ]);

        $product = Product::query()->visible()->findOrFail($data['product_id']);
        $variant = isset($data['variant_id'])
            ? ProductVariant::query()->where('product_id', $product->id)->find($data['variant_id'])
            : null;

        $wishlist = $manager->current($request->session(), $request->user(), create: true);
        $added = $manager->toggle($wishlist, $product, $variant);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $added ? __('Item saved to your wishlist!') : __('Item removed from your wishlist.'),
        ]);

        return back(303);
    }

    public function moveToCart(
        Request $request,
        int $itemId,
        WishlistManager $manager,
        CartManager $carts,
    ): RedirectResponse {
        $wishlist = $manager->current($request->session(), $request->user(), create: false);
        if ($wishlist === null) {
            abort(404);
        }

        $moved = $manager->moveToCart($wishlist, $itemId, $carts, $request->session(), $request->user());

        if ($moved) {
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Item moved to your shopping cart!'),
            ]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('Unable to move item to cart (item may be unavailable).'),
            ]);
        }

        return back(303);
    }

    public function destroy(
        Request $request,
        int $itemId,
        WishlistManager $manager,
    ): RedirectResponse {
        $wishlist = $manager->current($request->session(), $request->user(), create: false);
        if ($wishlist !== null) {
            $manager->remove($wishlist, $itemId);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Item removed from wishlist.'),
        ]);

        return back(303);
    }

    public function share(
        string $shareToken,
        WishlistSettings $settings,
        StoreSettings $store,
    ): Response {
        if (! $settings->enabled() || ! $settings->allowSharing()) {
            abort(404, 'Wishlist sharing is disabled.');
        }

        $wishlist = Wishlist::query()->where('share_token', $shareToken)->firstOrFail();
        $currency = $store->currency();
        $items = $this->formatWishlistItems($wishlist, $currency);

        return Inertia::render('module/modrixcommerce/wishlist/storefront/shared', [
            'wishlist' => [
                'name' => $wishlist->name,
                'items_count' => count($items),
            ],
            'items' => $items,
        ]);
    }

    private function formatWishlistItems(Wishlist $wishlist, string $currency): array
    {
        $items = [];
        $wishlist->load(['items.product.images', 'items.variant.prices']);

        foreach ($wishlist->items as $item) {
            $product = $item->product;
            if ($product === null) {
                continue;
            }

            $variant = $item->variant ?? $product->variants->first();
            $price = $variant?->priceIn($currency) ?? Money::zero($currency);

            $items[] = [
                'id' => $item->id,
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'name' => $product->localized('name'),
                'slug' => $product->slug,
                'image' => $product->images->first()?->url(),
                'sku' => $variant?->sku ?? '',
                'price' => (float) $price->toDisplayDecimal(),
                'formatted_price' => $price->format(),
                'is_active' => (bool) ($variant?->is_active ?? true),
                'created_at' => $item->created_at->diffForHumans(),
            ];
        }

        return $items;
    }
}

<?php

namespace EcoModular\Wishlist;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use EcoModular\Wishlist\Listeners\MergeWishlistOnLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Wishlist & Save For Later module.
 */
class WishlistServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/wishlist';

    public function register(): void
    {
        $this->app->singleton(WishlistSettings::class);
        $this->app->singleton(WishlistManager::class);
    }

    public function boot(StorefrontSlots $slots): void
    {
        // 1. Listen for user login to merge guest wishlist
        Event::listen(Login::class, MergeWishlistOnLogin::class);

        // 2. Register Wishlist Button on single product pages
        $slots->block(
            self::MODULE,
            'wishlist_button',
            'Wishlist Button',
            'WishlistButton',
            [
                'product.after_add_to_cart',
                'product.after_price',
                'product.after_details',
            ],
            'product.after_add_to_cart',
            function (array $context) {
                /** @var WishlistSettings $settings */
                $settings = $this->app->make(WishlistSettings::class);
                if (! $settings->enabled()) {
                    return null;
                }

                $product = $context['product'] ?? null;
                if (is_array($product) && isset($product['id'])) {
                    $product = Product::query()->find($product['id']);
                }

                if (! $product instanceof Product) {
                    return null;
                }

                /** @var WishlistManager $manager */
                $manager = $this->app->make(WishlistManager::class);
                $session = request()->session();
                $wishlist = $manager->current($session, request()->user(), create: false);

                $isWishlisted = $wishlist !== null && $wishlist->hasProduct($product);

                return [
                    'product_id' => $product->id,
                    'is_wishlisted' => $isWishlisted,
                    'button_label' => $settings->buttonLabel(),
                    'toggle_url' => route('modrixcommerce.wishlist.toggle'),
                    'wishlist_url' => route('modrixcommerce.wishlist.index'),
                ];
            }
        );

        // 3. Register Wishlist Header Icon in header actions
        $slots->block(
            self::MODULE,
            'wishlist_header_icon',
            'Wishlist Header Icon',
            'WishlistHeaderIcon',
            [
                'header.actions',
            ],
            'header.actions',
            function () {
                /** @var WishlistSettings $settings */
                $settings = $this->app->make(WishlistSettings::class);
                if (! $settings->enabled()) {
                    return null;
                }

                /** @var WishlistManager $manager */
                $manager = $this->app->make(WishlistManager::class);
                $wishlist = $manager->current(request()->session(), request()->user(), create: false);
                $count = $wishlist ? $wishlist->items()->count() : 0;

                return [
                    'count' => $count,
                    'wishlist_url' => route('modrixcommerce.wishlist.index'),
                ];
            }
        );
    }
}

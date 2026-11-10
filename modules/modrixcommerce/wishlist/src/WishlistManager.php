<?php

namespace EcoModular\Wishlist;

use App\Domains\Cart\CartManager;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Models\User;
use EcoModular\Wishlist\Models\Wishlist;
use EcoModular\Wishlist\Models\WishlistItem;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Service to manage customer and guest wishlists.
 */
class WishlistManager
{
    public const SESSION_TOKEN = 'wishlist_token';

    public function current(Session $session, ?User $user, bool $create = false): ?Wishlist
    {
        if ($user !== null) {
            $wishlist = Wishlist::query()->where('user_id', $user->id)->first();

            if ($wishlist === null && $create) {
                $wishlist = Wishlist::create([
                    'user_id' => $user->id,
                    'token' => null,
                    'name' => 'My Wishlist',
                    'share_token' => Wishlist::generateShareToken(),
                    'is_public' => false,
                ]);
            }

            return $wishlist;
        }

        $token = $session->get(self::SESSION_TOKEN);
        $wishlist = is_string($token)
            ? Wishlist::query()->where('token', $token)->whereNull('user_id')->first()
            : null;

        if ($wishlist === null && $create) {
            $token = Str::random(40);
            $wishlist = Wishlist::create([
                'user_id' => null,
                'token' => $token,
                'name' => 'My Wishlist',
                'share_token' => Wishlist::generateShareToken(),
                'is_public' => false,
            ]);
            $session->put(self::SESSION_TOKEN, $token);
        }

        return $wishlist;
    }

    public function toggle(Wishlist $wishlist, Product $product, ?ProductVariant $variant = null): bool
    {
        $existing = WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('product_id', $product->id)
            ->when($variant !== null, fn ($q) => $q->where('variant_id', $variant->id))
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false; // Removed
        }

        $defaultVariant = $variant ?? $product->variants()->where('is_active', true)->first();

        WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $product->id,
            'variant_id' => $defaultVariant?->id,
        ]);

        return true; // Added
    }

    public function remove(Wishlist $wishlist, int $itemId): void
    {
        WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('id', $itemId)
            ->delete();
    }

    public function moveToCart(Wishlist $wishlist, int $itemId, CartManager $carts, Session $session, ?User $user): bool
    {
        $item = WishlistItem::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('id', $itemId)
            ->with(['variant', 'product.variants'])
            ->first();

        if ($item === null) {
            return false;
        }

        $variant = $item->variant ?? $item->product->variants->where('is_active', true)->first();
        if ($variant === null || ! $variant->is_active) {
            return false;
        }

        $cart = $carts->current($session, $user, create: true);
        if ($cart === null) {
            return false;
        }

        DB::transaction(function () use ($carts, $cart, $variant, $item) {
            $carts->add($cart, $variant, 1);
            $item->delete();
        });

        return true;
    }

    public function mergeGuestWishlist(string $guestToken, User $user): void
    {
        $guestWishlist = Wishlist::query()
            ->where('token', $guestToken)
            ->whereNull('user_id')
            ->with('items')
            ->first();

        if ($guestWishlist === null || $guestWishlist->items->isEmpty()) {
            return;
        }

        $userWishlist = Wishlist::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'name' => 'My Wishlist',
                'share_token' => Wishlist::generateShareToken(),
                'is_public' => false,
            ]
        );

        DB::transaction(function () use ($guestWishlist, $userWishlist) {
            foreach ($guestWishlist->items as $item) {
                WishlistItem::query()->firstOrCreate(
                    [
                        'wishlist_id' => $userWishlist->id,
                        'product_id' => $item->product_id,
                        'variant_id' => $item->variant_id,
                    ],
                    [
                        'note' => $item->note,
                    ]
                );
            }

            $guestWishlist->delete();
        });
    }
}

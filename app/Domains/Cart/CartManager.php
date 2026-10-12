<?php

namespace App\Domains\Cart;

use App\Core\Settings\StoreSettings;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\VariantAvailability;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolves and mutates the current shopper's cart.
 *
 * Guest carts are identified by a random token held in the server-side
 * session (never by a client-supplied id). Authenticated users have one
 * persistent cart; a guest cart is merged into it on login.
 */
final class CartManager
{
    public const SESSION_KEY = 'cart_token';

    public const MAX_LINE_QUANTITY = 999;

    public const MAX_LINES = 100;

    public function __construct(private readonly StoreSettings $store) {}

    public function current(Session $session, ?User $user, bool $create = false): ?Cart
    {
        if ($user !== null) {
            return $create ? $this->forUser($user) : Cart::query()->where('user_id', $user->id)->latest('id')->first();
        }

        $token = $session->get(self::SESSION_KEY);
        $cart = is_string($token)
            ? Cart::query()->where('token', $token)->whereNull('user_id')->first()
            : null;

        if ($cart === null && $create) {
            $cart = $this->newCart(null);
            $session->put(self::SESSION_KEY, $cart->token);
        }

        return $cart;
    }

    public function add(Cart $cart, ProductVariant $variant, int $quantity): CartItem
    {
        return DB::transaction(function () use ($cart, $variant, $quantity) {
            $item = $cart->items()->where('variant_id', $variant->id)->lockForUpdate()->first();
            $newQuantity = ($item->quantity ?? 0) + $quantity;

            if ($item === null && $cart->items()->count() >= self::MAX_LINES) {
                throw ValidationException::withMessages(['quantity' => 'Your cart is full. Remove an item before adding another.']);
            }

            $this->assertQuantityAllowed($cart, $variant, $newQuantity);

            $item ??= new CartItem;
            $item->forceFill([
                'cart_id' => $cart->id,
                'variant_id' => $variant->id,
                'quantity' => $newQuantity,
                'seen_unit_price' => $variant->priceIn($cart->currency)?->toDecimal(),
            ])->save();

            $cart->touch();

            return $item;
        });
    }

    public function update(Cart $cart, CartItem $item, int $quantity): void
    {
        $this->assertBelongs($cart, $item);

        if ($quantity <= 0) {
            $this->remove($cart, $item);

            return;
        }

        $variant = $item->variant;
        $this->assertQuantityAllowed($cart, $variant, $quantity);
        $item->forceFill(['quantity' => $quantity])->save();
        $cart->touch();
    }

    public function remove(Cart $cart, CartItem $item): void
    {
        $this->assertBelongs($cart, $item);
        $item->delete();
        $cart->touch();
    }

    public function setCoupon(Cart $cart, ?string $code): void
    {
        $cart->forceFill(['coupon_code' => $code === null ? null : Str::upper(trim($code))])->save();
    }

    /**
     * Move a guest cart's lines into the user's cart (on login).
     * Quantities are capped at what is currently available.
     */
    public function mergeGuestCart(Session $session, User $user): void
    {
        $token = $session->get(self::SESSION_KEY);

        if (! is_string($token)) {
            return;
        }

        $guest = Cart::query()->where('token', $token)->whereNull('user_id')->with('items.variant.product', 'items.variant.prices', 'items.variant.stocks')->first();
        $session->forget(self::SESSION_KEY);

        if ($guest === null) {
            return;
        }

        DB::transaction(function () use ($guest, $user) {
            $target = $this->forUser($user);

            foreach ($guest->items as $item) {
                $existing = $target->items()->where('variant_id', $item->variant_id)->first();
                $wanted = min(self::MAX_LINE_QUANTITY, ($existing->quantity ?? 0) + $item->quantity);
                $availability = VariantAvailability::of($item->variant, $target->currency);
                $quantity = $availability->available === null ? $wanted : min($wanted, $availability->available);

                if ($quantity <= 0) {
                    continue;
                }

                ($existing ?? new CartItem)->forceFill([
                    'cart_id' => $target->id,
                    'variant_id' => $item->variant_id,
                    'quantity' => $quantity,
                    'seen_unit_price' => $item->seen_unit_price,
                ])->save();
            }

            if ($target->coupon_code === null && $guest->coupon_code !== null) {
                $target->forceFill(['coupon_code' => $guest->coupon_code])->save();
            }

            $guest->delete();
            $target->touch();
        });
    }

    public function empty(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->forceFill(['coupon_code' => null])->save();
    }

    public function forUser(User $user): Cart
    {
        return Cart::query()->where('user_id', $user->id)->latest('id')->first() ?? $this->newCart($user);
    }

    private function newCart(?User $user): Cart
    {
        $cart = new Cart;
        $cart->forceFill([
            'token' => (string) Str::uuid(),
            'user_id' => $user?->id,
            'currency' => $this->store->currency(),
        ])->save();

        return $cart;
    }

    private function assertQuantityAllowed(Cart $cart, ProductVariant $variant, int $quantity): void
    {
        if ($quantity > self::MAX_LINE_QUANTITY) {
            throw ValidationException::withMessages(['quantity' => 'You can order at most '.self::MAX_LINE_QUANTITY.' of an item.']);
        }

        $variant->loadMissing('product', 'prices', 'stocks');
        $availability = VariantAvailability::of($variant, $cart->currency);

        if (! $availability->purchasable) {
            throw ValidationException::withMessages(['quantity' => $availability->message() ?? 'This item is unavailable.']);
        }

        if (! $availability->allows($quantity)) {
            throw ValidationException::withMessages(['quantity' => "Only {$availability->available} available."]);
        }
    }

    private function assertBelongs(Cart $cart, CartItem $item): void
    {
        abort_unless($item->cart_id === $cart->id, 404);
    }
}

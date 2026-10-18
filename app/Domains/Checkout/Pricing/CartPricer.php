<?php

namespace App\Domains\Checkout\Pricing;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Pricing\SalePrices;
use App\Domains\Catalog\VariantAvailability;
use App\Domains\Pricing\Money;

/**
 * Prices a cart entirely from server-side data: catalog prices, current
 * availability, and (in later steps) coupons, shipping and tax.
 * Nothing the browser sends is used as an amount.
 */
final class CartPricer
{
    /** Steps that change line prices (e.g. a module's offer discounts) run first. */
    public const LINE_ADJUSTMENTS = 10;

    public const DISCOUNTS = 20;

    public const SHIPPING = 30;

    public const TAX = 40;

    /** Steps that only read the final amounts. */
    public const AFTER_TAX = 50;

    /** @var list<array{PricingStep, int}> */
    private array $steps = [];

    /**
     * Steps run by priority (lowest first), then in the order they were added.
     */
    public function addStep(PricingStep $step, int $priority = self::AFTER_TAX): void
    {
        $this->steps[] = [$step, $priority];
        // usort is stable, so equal priorities keep their order.
        usort($this->steps, fn (array $a, array $b) => $a[1] <=> $b[1]);
    }

    /**
     * @param  class-string<PricingStep>  $class
     */
    public function hasStep(string $class): bool
    {
        foreach ($this->steps as [$step]) {
            if ($step instanceof $class) {
                return true;
            }
        }

        return false;
    }

    public function price(Cart $cart, ?PricingContext $context = null): PricedCart
    {
        $context ??= new PricingContext;
        $cart->loadMissing([
            'items.variant.product.images',
            'items.variant.prices',
            'items.variant.stocks',
            'items.variant.product.categories:id',
            'items.variant.product.translations' => fn ($q) => $q->where('locale', app()->getLocale()),
        ]);

        $lines = [];
        // One sale-price lookup for the whole cart.
        app(SalePrices::class)->preload($cart->items->map(fn ($item) => $item->variant->product_id));

        foreach ($cart->items as $item) {
            $lines[] = $this->priceLine($item, $cart->currency);
        }

        $priced = new PricedCart($cart->currency, $lines);
        $priced->couponCode = $cart->coupon_code;

        foreach ($this->steps as [$step]) {
            $step->apply($priced, $cart, $context);
        }

        return $priced;
    }

    /**
     * Record the prices the shopper has now seen so a change is announced only once.
     */
    public function acknowledgePrices(PricedCart $priced): void
    {
        foreach ($priced->lines as $line) {
            if ($line->unitPrice !== null && ! $line->priceSeen) {
                CartItem::query()->whereKey($line->itemId)->update(['seen_unit_price' => $line->unitPrice->toDecimal()]);
            }
        }
    }

    private function priceLine(CartItem $item, string $currency): PricedLine
    {
        $variant = $item->variant;
        $availability = VariantAvailability::of($variant, $currency);
        $unitPrice = $variant->priceIn($currency);

        // A sale price from a module (e.g. a flash sale) is what the shopper pays.
        if ($unitPrice !== null) {
            $unitPrice = app(SalePrices::class)->apply($variant->product_id, $unitPrice)['price'] ?? $unitPrice;
        }

        $line = new PricedLine($item->id, $variant, $item->quantity, $unitPrice, $availability, $currency);

        if (! $availability->purchasable) {
            $line->notices[] = $availability->message() ?? st('This item is unavailable.');
        } elseif (! $availability->allows($item->quantity)) {
            $line->notices[] = st('Only :count available. Reduce the quantity to continue.', ['count' => (int) $availability->available]);
        }

        if ($unitPrice !== null && $item->seen_unit_price !== null) {
            $seen = Money::of($item->seen_unit_price, $currency);
            $line->priceSeen = $seen->equals($unitPrice);

            if (! $line->priceSeen) {
                $line->notices[] = st('The price changed from :from to :to since you added this item.', [
                    'from' => $seen->format(),
                    'to' => $unitPrice->format(),
                ]);
            }
        }

        return $line;
    }
}

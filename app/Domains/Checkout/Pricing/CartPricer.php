<?php

namespace App\Domains\Checkout\Pricing;

use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\VariantAvailability;
use App\Domains\Pricing\Money;

/**
 * Prices a cart entirely from server-side data: catalog prices, current
 * availability, and (in later steps) coupons, shipping and tax.
 * Nothing the browser sends is used as an amount.
 */
final class CartPricer
{
    /** @var list<PricingStep> */
    private array $steps = [];

    public function addStep(PricingStep $step): void
    {
        $this->steps[] = $step;
    }

    public function price(Cart $cart, ?PricingContext $context = null): PricedCart
    {
        $context ??= new PricingContext;
        $cart->loadMissing([
            'items.variant.product.images',
            'items.variant.prices',
            'items.variant.stocks',
            'items.variant.product.categories:id',
        ]);

        $lines = [];

        foreach ($cart->items as $item) {
            $lines[] = $this->priceLine($item, $cart->currency);
        }

        $priced = new PricedCart($cart->currency, $lines);
        $priced->couponCode = $cart->coupon_code;

        foreach ($this->steps as $step) {
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

        $line = new PricedLine($item->id, $variant, $item->quantity, $unitPrice, $availability, $currency);

        if (! $availability->purchasable) {
            $line->notices[] = $availability->message() ?? 'This item is unavailable.';
        } elseif (! $availability->allows($item->quantity)) {
            $line->notices[] = "Only {$availability->available} available. Reduce the quantity to continue.";
        }

        if ($unitPrice !== null && $item->seen_unit_price !== null) {
            $seen = Money::of($item->seen_unit_price, $currency);
            $line->priceSeen = $seen->equals($unitPrice);

            if (! $line->priceSeen) {
                $line->notices[] = sprintf(
                    'The price changed from %s to %s since you added this item.',
                    $seen->format(),
                    $unitPrice->format(),
                );
            }
        }

        return $line;
    }
}

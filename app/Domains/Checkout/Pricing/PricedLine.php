<?php

namespace App\Domains\Checkout\Pricing;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\VariantAvailability;
use App\Domains\Pricing\Money;

/**
 * One cart line priced from the catalog. Mutable only while the pricer
 * applies discounts and taxes.
 */
final class PricedLine
{
    /** @var list<string> */
    public array $notices = [];

    /** All discounts on the line: offer discounts plus its share of the coupon. */
    public Money $discount;

    /** The part of $discount from a module's offer (e.g. a cross-sell price). */
    public Money $offerDiscount;

    public ?string $offerLabel = null;

    public Money $tax;

    public string $taxRate = '0';

    /** Whether the shopper has already seen the current unit price (nothing to record). */
    public bool $priceSeen = false;

    public function __construct(
        public readonly int $itemId,
        public readonly ProductVariant $variant,
        public readonly int $quantity,
        public readonly ?Money $unitPrice,
        public readonly VariantAvailability $availability,
        string $currency,
    ) {
        $this->discount = Money::zero($currency);
        $this->offerDiscount = Money::zero($currency);
        $this->tax = Money::zero($currency);
    }

    public function isPurchasable(): bool
    {
        return $this->unitPrice !== null && $this->availability->allows($this->quantity);
    }

    /**
     * Unit price × quantity, rounded to the currency's minor units.
     */
    public function subtotal(): Money
    {
        return $this->unitPrice === null
            ? Money::zero($this->discount->currency())
            : $this->unitPrice->multipliedBy($this->quantity)->rounded();
    }

    /**
     * Lowers the line price by an offer discount (capped at what is left of the
     * line), counted in the cart's offer and discount totals.
     */
    public function applyOfferDiscount(PricedCart $cart, Money $amount, string $label): void
    {
        $amount = $amount->min($this->subtotal()->minus($this->discount))->nonNegative();

        if (! $amount->isPositive()) {
            return;
        }

        $this->discount = $this->discount->plus($amount);
        $this->offerDiscount = $this->offerDiscount->plus($amount);
        $this->offerLabel = $label;
        $cart->offerDiscountTotal = $cart->offerDiscountTotal->plus($amount);
        $cart->discountTotal = $cart->discountTotal->plus($amount);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $image = $this->variant->product->images->firstWhere('id', $this->variant->image_id)
            ?? $this->variant->product->images->first();

        return [
            'id' => $this->itemId,
            'variant_id' => $this->variant->id,
            'product_name' => $this->variant->product->localized('name'),
            'product_slug' => $this->variant->product->slug,
            'variant_name' => $this->variant->name,
            'sku' => $this->variant->sku,
            'image' => $image ? ['url' => $image->url(), 'alt' => $image->alt] : null,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'subtotal' => $this->subtotal(),
            'discount' => $this->discount,
            'offer_discount' => $this->offerDiscount,
            'offer_label' => $this->offerLabel,
            'purchasable' => $this->isPurchasable(),
            'available' => $this->availability->available,
            'notices' => $this->notices,
        ];
    }
}

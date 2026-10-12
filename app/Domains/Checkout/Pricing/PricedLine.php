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

    public Money $discount;

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
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $image = $this->variant->product->images->first();

        return [
            'id' => $this->itemId,
            'variant_id' => $this->variant->id,
            'product_name' => $this->variant->product->name,
            'product_slug' => $this->variant->product->slug,
            'variant_name' => $this->variant->name,
            'sku' => $this->variant->sku,
            'image' => $image ? ['url' => $image->url(), 'alt' => $image->alt] : null,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
            'subtotal' => $this->subtotal(),
            'discount' => $this->discount,
            'purchasable' => $this->isPurchasable(),
            'available' => $this->availability->available,
            'notices' => $this->notices,
        ];
    }
}

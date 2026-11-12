<?php

namespace App\Domains\Catalog;

use App\Domains\Catalog\Models\ProductVariant;

/**
 * Whether a variant can be bought right now, and how many.
 * Used by the storefront, the cart and checkout so all three agree.
 */
final class VariantAvailability
{
    public const INACTIVE = 'inactive';

    public const UNPRICED = 'unpriced';

    public const OUT_OF_STOCK = 'out_of_stock';

    /**
     * @param  bool  $purchasable  can be added to a cart
     * @param  int|null  $available  maximum quantity, null = unlimited
     */
    private function __construct(
        public readonly bool $purchasable,
        public readonly ?string $reason,
        public readonly ?int $available,
    ) {}

    /**
     * Expects `product`, `prices` and `stocks` relations to be loaded for efficiency.
     */
    public static function of(ProductVariant $variant, string $currency): self
    {
        if ($variant->trashed() || ! $variant->is_active || ! $variant->product->isVisible()) {
            return new self(false, self::INACTIVE, 0);
        }

        if ($variant->priceIn($currency) === null) {
            return new self(false, self::UNPRICED, 0);
        }

        if (! $variant->track_inventory || $variant->allow_backorder) {
            return new self(true, null, null);
        }

        $onHand = max(0, (int) $variant->onHand());

        return $onHand > 0
            ? new self(true, null, $onHand)
            : new self(false, self::OUT_OF_STOCK, 0);
    }

    public function allows(int $quantity): bool
    {
        return $this->purchasable && ($this->available === null || $quantity <= $this->available);
    }

    public function message(): ?string
    {
        return match ($this->reason) {
            self::INACTIVE => st('This item is no longer available.'),
            self::UNPRICED => st('This item cannot be purchased in the store currency.'),
            self::OUT_OF_STOCK => st('This item is out of stock.'),
            default => null,
        };
    }
}

<?php

namespace App\Domains\Checkout\Pricing;

use App\Domains\Pricing\Money;

/**
 * Result of pricing a cart: the single source of truth for every amount the
 * shopper sees and for the order that checkout creates.
 */
final class PricedCart
{
    /** @var list<string> */
    public array $issues = [];

    public Money $discountTotal;

    public Money $shippingTotal;

    public Money $shippingTax;

    public Money $taxTotal;

    public ?string $couponCode = null;

    public ?string $couponError = null;

    /** @var array{code: string, label: string, amount: Money}|null */
    public ?array $shipping = null;

    /** @var list<array{code: string, label: string, amount: Money}> */
    public array $shippingOptions = [];

    public bool $pricesIncludeTax = false;

    /**
     * @param  list<PricedLine>  $lines
     */
    public function __construct(
        public readonly string $currency,
        public readonly array $lines,
    ) {
        $this->discountTotal = Money::zero($currency);
        $this->shippingTotal = Money::zero($currency);
        $this->shippingTax = Money::zero($currency);
        $this->taxTotal = Money::zero($currency);
    }

    public function subtotal(): Money
    {
        $total = Money::zero($this->currency);

        foreach ($this->purchasableLines() as $line) {
            $total = $total->plus($line->subtotal());
        }

        return $total;
    }

    /**
     * Subtotal − discounts + shipping (+ tax when prices exclude tax).
     */
    public function grandTotal(): Money
    {
        $total = $this->subtotal()->minus($this->discountTotal)->plus($this->shippingTotal);

        return ($this->pricesIncludeTax ? $total : $total->plus($this->taxTotal))->nonNegative();
    }

    /**
     * @return list<PricedLine>
     */
    public function purchasableLines(): array
    {
        return array_values(array_filter($this->lines, fn (PricedLine $l) => $l->isPurchasable()));
    }

    /**
     * Whether anything needs shipping: false when every item is a digital
     * product (delivered as downloads).
     */
    public function requiresShipping(): bool
    {
        foreach ($this->purchasableLines() as $line) {
            if (! $line->variant->product->is_digital) {
                return true;
            }
        }

        return false;
    }

    public function itemCount(): int
    {
        return array_sum(array_map(fn (PricedLine $l) => $l->quantity, $this->lines));
    }

    public function isEmpty(): bool
    {
        return $this->lines === [];
    }

    /**
     * Checkout is possible only when every line can be bought as shown.
     */
    public function canCheckout(): bool
    {
        return ! $this->isEmpty()
            && count($this->purchasableLines()) === count($this->lines)
            && $this->issues === [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'lines' => array_map(fn (PricedLine $l) => $l->toArray(), $this->lines),
            'item_count' => $this->itemCount(),
            'subtotal' => $this->subtotal(),
            'discount_total' => $this->discountTotal,
            'shipping_total' => $this->shippingTotal,
            'tax_total' => $this->taxTotal,
            'grand_total' => $this->grandTotal(),
            'prices_include_tax' => $this->pricesIncludeTax,
            'coupon_code' => $this->couponCode,
            'coupon_error' => $this->couponError,
            'shipping' => $this->shipping,
            'shipping_options' => $this->shippingOptions,
            'requires_shipping' => $this->requiresShipping(),
            'issues' => $this->issues,
            'can_checkout' => $this->canCheckout(),
        ];
    }
}

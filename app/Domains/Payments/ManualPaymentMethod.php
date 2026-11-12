<?php

namespace App\Domains\Payments;

use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Payments\Contracts\PaymentMethod;

/**
 * Offline payment (bank transfer, cash on delivery). Explicitly configured by
 * the merchant; never a stand-in for an online gateway.
 */
final class ManualPaymentMethod implements PaymentMethod
{
    public function __construct(
        private readonly string $code,
        private readonly string $label,
        private readonly ?string $instructions,
        private readonly bool $payOnDelivery,
    ) {}

    public function code(): string
    {
        return $this->code;
    }

    public function label(): string
    {
        return $this->label;
    }

    public function instructions(): ?string
    {
        return $this->instructions;
    }

    public function isAvailable(PricedCart $cart): bool
    {
        return true;
    }

    public function allowsFulfillmentBeforePayment(): bool
    {
        return $this->payOnDelivery;
    }
}

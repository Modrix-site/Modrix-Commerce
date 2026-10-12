<?php

namespace App\Domains\Checkout;

/**
 * Validated checkout input. Contains identifiers and contact/address data
 * only; no amounts except `expectedTotal`, which is used solely to detect
 * that prices changed while the shopper was on the checkout page.
 */
final class CheckoutData
{
    /**
     * @param  array<string, string|null>  $shippingAddress
     * @param  array<string, string|null>|null  $billingAddress  null = same as shipping
     */
    public function __construct(
        public readonly string $email,
        public readonly ?string $phone,
        public readonly array $shippingAddress,
        public readonly ?array $billingAddress,
        public readonly string $shippingMethod,
        public readonly string $paymentMethod,
        public readonly ?string $customerNote,
        public readonly string $idempotencyKey,
        public readonly ?string $expectedTotal,
        public readonly bool $saveAddress = false,
    ) {}
}

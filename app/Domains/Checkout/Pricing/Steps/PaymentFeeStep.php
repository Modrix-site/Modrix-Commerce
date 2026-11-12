<?php

namespace App\Domains\Checkout\Pricing\Steps;

use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use App\Domains\Payments\PaymentMethods;

final class PaymentFeeStep implements PricingStep
{
    public function __construct(private readonly PaymentMethods $methods) {}

    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        $priced->paymentMethod = $context->paymentMethod;

        // This is a separate courier collection charge, not merchandise or shipping.
        if ($context->paymentMethod === 'cash_on_delivery' && $priced->requiresShipping()) {
            $priced->paymentFeeTotal = $this->methods->cashOnDeliveryFee($priced->currency);
        }
    }
}

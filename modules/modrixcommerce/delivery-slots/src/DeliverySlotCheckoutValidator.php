<?php

namespace EcoModular\DeliverySlots;

use App\Domains\Checkout\CheckoutData;
use App\Domains\Checkout\Contracts\CheckoutValidator;
use App\Domains\Checkout\Pricing\PricedCart;

/**
 * Validates delivery slot selection at checkout when configured as required.
 */
class DeliverySlotCheckoutValidator implements CheckoutValidator
{
    public function __construct(
        private readonly DeliverySlotsSettings $settings,
        private readonly DeliverySlotResolver $resolver,
    ) {}

    public function validate(PricedCart $cart, CheckoutData $data): ?string
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        // Store pickup orders are collected, not delivered: no slot needed.
        if ($data->shippingMethod === 'store_pickup' || str_starts_with($data->shippingMethod, 'pickup_')) {
            return null;
        }

        $rule = $this->settings->ruleForMethod($data->shippingMethod);

        // If delivery slots or time slots are disabled for this shipping method (e.g. 1-Day Instant Delivery)
        if (! $rule['enabled'] || ! $rule['show_time_slots']) {
            return null;
        }

        $selection = session('delivery_slots_selection');

        if (empty($selection) || empty($selection['delivery_slot_id']) || empty($selection['date'])) {
            if ($this->settings->isRequired()) {
                return 'Please select a preferred delivery date and time slot before placing your order.';
            }

            return null;
        }

        $check = $this->resolver->validateSelection(
            (string) $selection['date'],
            (int) $selection['delivery_slot_id'],
            (int) $rule['max_days'],
        );

        if (! $check['valid']) {
            return $check['error'] ?? 'Selected delivery slot is no longer available. Please choose another slot.';
        }

        return null;
    }
}

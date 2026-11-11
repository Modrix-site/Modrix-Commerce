<?php

namespace EcoModular\StorePickup;

use App\Domains\Checkout\CheckoutData;
use App\Domains\Checkout\Contracts\CheckoutValidator;
use App\Domains\Checkout\Pricing\PricedCart;
use EcoModular\StorePickup\Models\PickupBranch;

/**
 * Validates branch selection when a customer selects in-store pickup.
 */
class StorePickupCheckoutValidator implements CheckoutValidator
{
    public function __construct(
        private readonly StorePickupSettings $settings,
    ) {}

    public function validate(PricedCart $cart, CheckoutData $data): ?string
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        $method = $data->shippingMethod;
        $isPickup = $method === StorePickupShippingRateProvider::METHOD_CODE
            || str_starts_with($method, 'pickup_');

        if ($isPickup && $this->settings->codBehavior() === StorePickupSettings::COD_HIDE && $data->paymentMethod === 'cash_on_delivery') {
            return 'Cash on delivery is not available for store pickup orders. Please choose another payment method.';
        }

        // Check if the order is using a branch-specific pickup code
        if (str_starts_with($method, 'pickup_')) {
            $branchId = (int) substr($method, 7);
            $branch = PickupBranch::query()->where('id', $branchId)->where('is_active', true)->first();

            if (! $branch) {
                return 'The selected pickup branch is currently unavailable. Please choose another location.';
            }

            return null;
        }

        // Check if the order is using the unified store_pickup method
        if ($method === StorePickupShippingRateProvider::METHOD_CODE) {
            $selection = session('store_pickup_selection');

            if (! empty($selection['branch_id'])) {
                $branch = PickupBranch::query()->where('id', $selection['branch_id'])->where('is_active', true)->first();
                if (! $branch) {
                    return 'The selected pickup branch is no longer available. Please select another branch.';
                }

                return null;
            }

            // If there's only 1 active branch in the store, automatically permit it
            $activeBranches = PickupBranch::active()->get();
            if ($activeBranches->count() === 1) {
                return null;
            }

            return 'Please select a store or branch location to collect your order.';
        }

        return null;
    }
}

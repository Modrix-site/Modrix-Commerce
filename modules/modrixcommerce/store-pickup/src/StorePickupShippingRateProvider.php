<?php

namespace EcoModular\StorePickup;

use App\Domains\Pricing\Money;
use App\Domains\Shipping\Contracts\ShippingRateProvider;
use App\Domains\Shipping\ShippingRate;
use App\Domains\Shipping\ShippingRequest;
use EcoModular\StorePickup\Models\PickupBranch;

/**
 * Provides in-store and branch pickup shipping rates to checkout.
 */
class StorePickupShippingRateProvider implements ShippingRateProvider
{
    public const METHOD_CODE = 'store_pickup';

    public function __construct(
        private readonly StorePickupSettings $settings,
    ) {}

    /**
     * @return list<ShippingRate>
     */
    public function rates(ShippingRequest $request): array
    {
        if (! $this->settings->enabled()) {
            return [];
        }

        $branches = PickupBranch::active()->get();

        if ($branches->isEmpty()) {
            return [];
        }

        $selection = session('store_pickup_selection');
        $selectedBranch = null;
        if (! empty($selection['branch_id'])) {
            $selectedBranch = $branches->firstWhere('id', (int) $selection['branch_id']);
        }

        $fee = $selectedBranch ? $selectedBranch->fee : ($branches->min('fee') ?: '0.0000');

        $description = match (true) {
            $selectedBranch !== null => $selectedBranch->name.' ('.$selectedBranch->city.') · '.($selectedBranch->prep_time ?: 'Ready in 2 hours'),
            $branches->count() === 1 => 'Pick up at '.$branches->first()->name.' ('.$branches->first()->city.')',
            default => 'Select your preferred pickup branch at checkout ('.$branches->count().' locations available)',
        };

        return [
            new ShippingRate(
                code: self::METHOD_CODE,
                label: $this->settings->shippingLabel(),
                amount: Money::of((string) $fee, $request->currency),
                description: $description,
            ),
        ];
    }
}

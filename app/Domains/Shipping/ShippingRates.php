<?php

namespace App\Domains\Shipping;

use App\Domains\Shipping\Contracts\ShippingRateProvider;
use InvalidArgumentException;

/**
 * Registry of shipping rate providers (core + modules).
 */
final class ShippingRates
{
    /** @var list<ShippingRateProvider> */
    private array $providers = [];

    public function register(ShippingRateProvider $provider): void
    {
        $this->providers[] = $provider;
    }

    /**
     * @return list<ShippingRate>
     */
    public function quote(ShippingRequest $request): array
    {
        if (! app(DeliveryArea::class)->allows($request->countryCode, $request->region, $request->city)) {
            return [];
        }

        $rates = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->rates($request) as $rate) {
                if (isset($rates[$rate->code])) {
                    throw new InvalidArgumentException("Duplicate shipping method code [{$rate->code}].");
                }

                if ($rate->amount->currency() !== $request->currency || $rate->amount->isNegative()) {
                    continue; // a provider must quote in the cart currency and never pay the customer
                }

                $rates[$rate->code] = $rate;
            }
        }

        return array_values($rates);
    }
}

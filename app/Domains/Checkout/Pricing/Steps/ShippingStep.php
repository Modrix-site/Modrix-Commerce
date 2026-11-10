<?php

namespace App\Domains\Checkout\Pricing\Steps;

use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Domains\Cart\Models\Cart;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Checkout\Pricing\PricingStep;
use App\Domains\Pricing\Money;
use App\Domains\Shipping\DeliveryArea;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Domains\Shipping\ShippingRate;
use App\Domains\Shipping\ShippingRates;
use App\Domains\Shipping\ShippingRequest;
use Brick\Math\BigDecimal;

/**
 * Quotes shipping for the destination and applies the chosen method.
 * Without a destination no options are offered yet (shown as "at checkout").
 */
final class ShippingStep implements PricingStep
{
    /** Shipping code of orders with only digital products. */
    public const DIGITAL = 'digital';

    public function __construct(private readonly ShippingRates $rates) {}

    public function apply(PricedCart $priced, Cart $cart, PricingContext $context): void
    {
        if ($priced->purchasableLines() === []) {
            return;
        }

        // Digital products only: nothing to ship, whatever the destination.
        if (! $priced->requiresShipping()) {
            $none = new ShippingRate(self::DIGITAL, 'No shipping needed', Money::zero($priced->currency));
            $priced->shippingOptions = [$none->toArray()];
            $priced->shipping = $none->toArray();
            $priced->shippingTotal = $none->amount;

            return;
        }

        if ($context->countryCode === null) {
            return;
        }

        $weight = BigDecimal::zero();
        $items = 0;

        foreach ($priced->purchasableLines() as $line) {
            $weight = $weight->plus(BigDecimal::of($line->variant->weight ?? '0')->multipliedBy($line->quantity));
            $items += $line->quantity;
        }

        $options = $this->rates->quote(new ShippingRequest(
            currency: $priced->currency,
            countryCode: $context->countryCode,
            region: $context->region,
            postalCode: $context->postalCode,
            merchandiseTotal: $priced->subtotal()->minus($priced->discountTotal),
            totalWeight: (string) $weight,
            itemCount: $items,
            city: $context->city,
        ));

        $priced->shippingOptions = array_map(fn (ShippingRate $r) => $r->toArray(), $options);

        if ($options === []) {
            $priced->issues[] = $this->awaitingPlace($priced->currency, $context)
                ? st('Add your state or city to see the shipping method and amount.')
                : 'We do not ship to the selected destination.';

            return;
        }

        $selected = null;

        foreach ($options as $option) {
            if ($option->code === $context->shippingMethod) {
                $selected = $option;
            }
        }

        if ($selected !== null) {
            $priced->shipping = $selected->toArray();
            $priced->shippingTotal = $selected->amount;
        }
    }

    /**
     * The country is already chosen, and a rate exists only after a state or city.
     */
    private function awaitingPlace(string $currency, PricingContext $context): bool
    {
        $area = app(DeliveryArea::class);
        $country = strtoupper((string) $context->countryCode);
        $covered = $area->countries() === null || in_array($country, $area->countries(), true);
        if (! $covered) {
            return false;
        }

        $region = trim((string) $context->region);
        $city = trim((string) $context->city);
        $mode = $area->toArray()['mode'];
        if ($mode === 'store_city' && $city === '') {
            return true;
        }
        if (in_array($mode, ['store_region', 'store_city'], true) && $region === '' && (string) app(SettingsRepository::class)->get('store.region', '') !== '') {
            return true;
        }
        if ($region !== '') {
            return false;
        }

        $methods = ShippingMethod::query()->where('is_active', true)->get();
        $matched = $methods->where('currency', $currency);
        if ($matched->isEmpty()) {
            $matched = $methods->where('currency', app(StoreSettings::class)->currency());
        }

        return $matched->contains(fn (ShippingMethod $method) => $method->regions_only && $method->shipsTo($country));
    }
}

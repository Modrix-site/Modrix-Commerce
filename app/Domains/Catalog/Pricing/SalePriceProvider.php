<?php

namespace App\Domains\Catalog\Pricing;

use Carbon\CarbonInterface;

/**
 * Extension point (capability "catalog.sale_prices"): a module that lowers
 * product prices for a while, such as a flash sale. Register with
 * SalePrices::addProvider() from the module's service provider.
 *
 * Offers are read on every storefront page, in the cart and at checkout, so
 * the shown and the charged price are always the same.
 */
interface SalePriceProvider
{
    /**
     * Offers valid at `$now`, keyed by product id (products without one are
     * left out). A product may have several; the lowest resulting price wins.
     *
     * @param  list<int>  $productIds
     * @return array<int, SaleOffer|list<SaleOffer>>
     */
    public function offers(array $productIds, CarbonInterface $now): array;
}

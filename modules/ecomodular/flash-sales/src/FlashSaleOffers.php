<?php

namespace EcoModular\FlashSales;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Pricing\SaleOffer;
use App\Domains\Catalog\Pricing\SalePriceProvider;
use Carbon\CarbonInterface;
use EcoModular\FlashSales\Models\FlashSale;
use EcoModular\FlashSales\Models\FlashSaleProduct;

/**
 * Turns live flash sales into catalog sale offers. Fixed amounts are in the
 * store currency.
 */
final class FlashSaleOffers implements SalePriceProvider
{
    public function __construct(private readonly StoreSettings $store) {}

    public function offers(array $productIds, CarbonInterface $now): array
    {
        if ($productIds === []) {
            return [];
        }

        $items = FlashSaleProduct::query()
            ->whereIn('product_id', $productIds)
            ->whereHas('sale', fn ($q) => $q->where('is_active', true)->where('starts_at', '<=', $now)->where('ends_at', '>', $now))
            ->with('sale')
            ->get();

        $currency = $this->store->currency();
        $offers = [];

        // A product can be in several live sales; the core keeps the lowest price.
        foreach ($items as $item) {
            $fixed = $item->effectiveType() === FlashSale::FIXED;
            $offers[$item->product_id][] = new SaleOffer(
                $fixed ? SaleOffer::FIXED : SaleOffer::PERCENT,
                $item->effectiveValue(),
                $item->sale->name,
                $item->sale->ends_at,
                $fixed ? $currency : null,
            );
        }

        return $offers;
    }
}

<?php

namespace App\Domains\Catalog;

use App\Domains\Checkout\Pricing\PricedCart;
use Illuminate\Support\Facades\DB;

/**
 * Products to suggest on the cart page: the merchant's related products of
 * the cart's products, in their order, without products already in the
 * cart or not for sale.
 */
final class CartCrossSells
{
    public function __construct(private readonly StorefrontCatalog $catalog) {}

    /**
     * @return list<array<string, mixed>> product cards
     */
    public function for(PricedCart $cart, int $limit = 4): array
    {
        $inCart = array_values(array_unique(array_map(fn ($line) => (int) $line->variant->product_id, $cart->lines)));

        if ($inCart === []) {
            return [];
        }

        $related = array_values(array_unique(array_map('intval', DB::table('product_relations')
            ->whereIn('product_id', $inCart)
            ->where('type', ProductRelations::RELATED)
            ->whereNotIn('related_product_id', $inCart)
            ->orderBy('position')
            ->pluck('related_product_id')
            ->all())));

        return $this->catalog->cardsFor($related, $cart->currency, $limit);
    }
}

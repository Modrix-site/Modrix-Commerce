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

        $rows = DB::table('product_relations')
            ->whereIn('product_id', $inCart)
            ->where('type', ProductRelations::RELATED)
            ->whereNotIn('related_product_id', $inCart)
            ->get(['product_id', 'related_product_id', 'position']);
        // In the cart's order, then each product's own order.
        $order = array_flip($inCart);
        $sorted = $rows->sortBy([
            fn ($a, $b) => $order[(int) $a->product_id] <=> $order[(int) $b->product_id],
            fn ($a, $b) => (int) $a->position <=> (int) $b->position,
        ]);
        $related = array_values(array_unique(array_map('intval', $sorted->pluck('related_product_id')->all())));

        return $this->catalog->cardsFor($related, $cart->currency, $limit);
    }
}

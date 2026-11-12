<?php

namespace App\Domains\Catalog;

use App\Domains\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Merchant-picked related products ("You may also like") and upsells
 * ("Customers also bought"), kept in the order they were chosen.
 */
final class ProductRelations
{
    public const RELATED = 'related';

    public const UPSELL = 'upsell';

    public const TYPES = [self::RELATED, self::UPSELL];

    /** Most products of each type. */
    public const MAX = 12;

    /**
     * Replaces both lists. The product itself and repeated ids are dropped.
     *
     * @param  list<int>  $relatedIds
     * @param  list<int>  $upsellIds
     */
    public function sync(Product $product, array $relatedIds, array $upsellIds): void
    {
        DB::transaction(function () use ($product, $relatedIds, $upsellIds) {
            DB::table('product_relations')->where('product_id', $product->id)->delete();
            $now = now();
            $rows = [];

            foreach ([self::RELATED => $relatedIds, self::UPSELL => $upsellIds] as $type => $ids) {
                $ids = array_slice(array_values(array_unique(array_filter(array_map('intval', $ids), fn (int $id) => $id !== $product->id))), 0, self::MAX);

                foreach ($ids as $position => $id) {
                    $rows[] = ['product_id' => $product->id, 'related_product_id' => $id, 'type' => $type, 'position' => $position, 'created_at' => $now, 'updated_at' => $now];
                }
            }

            if ($rows !== []) {
                DB::table('product_relations')->insert($rows);
            }
        });
    }
}

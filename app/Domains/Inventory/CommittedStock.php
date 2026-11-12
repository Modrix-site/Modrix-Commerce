<?php

namespace App\Domains\Inventory;

use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;

/**
 * Units sold but not yet shipped, per variant ("committed" in the admin).
 *
 * Stock leaves `on_hand` when an order is placed, so committed units are no
 * longer on the shelf count: the physical quantity is on_hand + committed.
 * Cancelled orders and restocked units are not committed.
 */
final class CommittedStock
{
    /**
     * @param  list<int>  $variantIds
     * @return array<int, int> variant id => committed units (only variants with any)
     */
    public function forVariants(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $beforeShipping = array_map(
            fn (FulfillmentStatus $s) => $s->value,
            array_filter(FulfillmentStatus::cases(), fn (FulfillmentStatus $s) => $s->isBeforeShipping()),
        );

        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('order_items.variant_id', $variantIds)
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->whereIn('orders.fulfillment_status', array_values($beforeShipping))
            ->groupBy('order_items.variant_id')
            ->selectRaw('order_items.variant_id as variant_id, SUM(order_items.quantity - order_items.restocked_quantity) as committed')
            ->pluck('committed', 'variant_id')
            ->map(fn ($qty) => max(0, (int) $qty))
            ->filter()
            ->all();
    }
}

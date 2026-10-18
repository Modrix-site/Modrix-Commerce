<?php

namespace EcoModular\CrossSellUpsell;

use App\Domains\Pricing\Money;
use EcoModular\CrossSellUpsell\Models\Offer;
use Illuminate\Support\Facades\DB;

/**
 * Views (once per shopper session, offer and place), adds and orders.
 */
class OfferStats
{
    private const SESSION_KEY = 'cross_sell.viewed';

    public function viewed(Offer $offer, string $place): void
    {
        if (! app()->bound('request') || ! request()->hasSession()) {
            return;
        }

        $session = request()->session();
        $seen = (array) $session->get(self::SESSION_KEY, []);
        $key = "{$offer->id}:{$place}";

        if (in_array($key, $seen, true)) {
            return;
        }

        // Keep the list short; the oldest entries are counted again, rarely.
        $session->put(self::SESSION_KEY, array_slice([...$seen, $key], -100));
        Offer::query()->whereKey($offer->id)->increment('views');
    }

    /**
     * Links the order to the offers its lines were added from (runs inside
     * the order transaction, before the cart is emptied).
     */
    public function recordOrder(int $cartId, int $orderId): void
    {
        $rows = DB::table('cross_sell_cart_lines')->where('cart_id', $cartId)->get(['variant_id', 'offer_id']);

        if ($rows->isEmpty()) {
            return;
        }

        $items = DB::table('order_items')->where('order_id', $orderId)->whereIn('variant_id', $rows->pluck('variant_id'))->get();
        $currency = (string) DB::table('orders')->where('id', $orderId)->value('currency');
        $offers = $rows->pluck('offer_id', 'variant_id');
        $conversions = [];

        foreach ($items as $item) {
            $offerId = (int) $offers->get($item->variant_id);
            $revenue = Money::of((string) $item->subtotal, $currency)->minus(Money::of((string) $item->discount_total, $currency));
            $conversions[] = [
                'offer_id' => $offerId,
                'order_id' => $orderId,
                'product_id' => (int) $item->product_id,
                'quantity' => (int) $item->quantity,
                'revenue' => $revenue->toDecimal(),
                'currency' => $currency,
                'created_at' => now(),
            ];
        }

        if ($conversions !== []) {
            // Listeners may run twice (e.g. a retried event); keep one row.
            DB::table('cross_sell_conversions')->insertOrIgnore($conversions);
        }

        DB::table('cross_sell_cart_lines')->where('cart_id', $cartId)->delete();
    }

    /**
     * Orders and revenue (per currency) for each offer.
     *
     * @return array<int, array{orders: int, revenue: array<string, string>}>
     */
    public function results(): array
    {
        $results = [];

        foreach (DB::table('cross_sell_conversions')->selectRaw('offer_id, currency, COUNT(DISTINCT order_id) as orders, SUM(revenue) as revenue')->groupBy('offer_id', 'currency')->get() as $row) {
            $id = (int) $row->offer_id;
            $results[$id] ??= ['orders' => 0, 'revenue' => []];
            $results[$id]['orders'] += (int) $row->orders;
            $results[$id]['revenue'][(string) $row->currency] = Money::of((string) $row->revenue, (string) $row->currency)->rounded()->toDisplayDecimal();
        }

        return $results;
    }
}

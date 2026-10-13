<?php

namespace App\Domains\Reports;

use App\Core\Settings\SettingsRepository;
use App\Domains\Inventory\InventoryService;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Pricing\Money;
use Brick\Math\RoundingMode;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;

/**
 * Sales reporting from persisted orders, bucketed in the store timezone.
 *
 * "Sales" are the grand totals of orders placed in the period that were not
 * cancelled; refunds are reported separately by the date they were made.
 * Amounts are summed with Money (never floats). Version 1 aggregates in PHP,
 * which suits small and medium stores; larger stores should move these
 * aggregates to summary tables (see docs/roadmap.md).
 */
final class SalesReport
{
    public const MAX_DAYS = 366;

    public function __construct(
        private readonly SettingsRepository $settings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to, string $timezone, string $currency): array
    {
        $start = $from->setTimezone($timezone)->startOfDay();
        $end = $to->setTimezone($timezone)->endOfDay();

        $orders = Order::query()
            ->where('currency', $currency)
            ->whereBetween('placed_at', [$start->utc(), $end->utc()])
            ->get(['id', 'status', 'grand_total', 'placed_at', 'currency']);

        $days = [];

        foreach (CarbonPeriod::create($start, '1 day', $end->startOfDay()) as $day) {
            $days[$day->format('Y-m-d')] = ['date' => $day->format('Y-m-d'), 'orders' => 0, 'sales' => Money::zero($currency)];
        }

        $gross = Money::zero($currency);
        $count = 0;
        $byStatus = array_fill_keys(array_map(fn (OrderStatus $s) => $s->value, OrderStatus::cases()), 0);

        foreach ($orders as $order) {
            $byStatus[$order->status->value]++;

            if ($order->status === OrderStatus::Cancelled) {
                continue;
            }

            $key = $order->placed_at->setTimezone($timezone)->format('Y-m-d');
            $amount = $order->money('grand_total');

            if (isset($days[$key])) {
                $days[$key]['orders']++;
                $days[$key]['sales'] = $days[$key]['sales']->plus($amount);
            }

            $gross = $gross->plus($amount);
            $count++;
        }

        $refunds = Money::zero($currency);

        foreach (Refund::query()->where('currency', $currency)->whereBetween('created_at', [$start->utc(), $end->utc()])->pluck('amount') as $amount) {
            $refunds = $refunds->plus(Money::of((string) $amount, $currency));
        }

        return [
            'from' => $start->format('Y-m-d'),
            'to' => $end->format('Y-m-d'),
            'timezone' => $timezone,
            'currency' => $currency,
            'gross_sales' => $gross,
            'orders' => $count,
            'average_order_value' => $count > 0
                ? Money::of($gross->amount()->dividedBy($count, 4, RoundingMode::HALF_UP), $currency)->rounded()
                : null,
            'refunds' => $refunds,
            'net_sales' => $gross->minus($refunds),
            'orders_by_status' => $byStatus,
            'series' => array_values($days),
            'top_products' => $this->topProducts(array_values(array_map('intval', $orders->where('status', '!=', OrderStatus::Cancelled)->pluck('id')->all())), $currency),
        ];
    }

    /**
     * @param  list<int>  $orderIds
     * @return list<array{sku: string, name: string, quantity: int, revenue: Money}>
     */
    private function topProducts(array $orderIds, string $currency): array
    {
        if ($orderIds === []) {
            return [];
        }

        $rows = DB::table('order_items')
            ->whereIn('order_id', $orderIds)
            ->select('sku', 'product_name', 'variant_name', 'quantity', 'subtotal', 'discount_total')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $sku = (string) $row->sku;
            $grouped[$sku] ??= [
                'sku' => $sku,
                'name' => trim($row->product_name.($row->variant_name ? " — {$row->variant_name}" : '')),
                'quantity' => 0,
                'revenue' => Money::zero($currency),
            ];
            $grouped[$sku]['quantity'] += (int) $row->quantity;
            $grouped[$sku]['revenue'] = $grouped[$sku]['revenue']
                ->plus(Money::of((string) $row->subtotal, $currency))
                ->minus(Money::of((string) $row->discount_total, $currency));
        }

        usort($grouped, fn ($a, $b) => $b['quantity'] <=> $a['quantity'] ?: $b['revenue']->compareTo($a['revenue']));

        return array_slice($grouped, 0, 10);
    }

    /**
     * Counts for the admin dashboard.
     *
     * @return array{pending_payment: int, awaiting_fulfillment: int, low_stock: int}
     */
    public function operationalCounts(InventoryService $inventory): array
    {
        $threshold = (int) $this->settings->get('inventory.low_stock_threshold', InventoryService::DEFAULT_LOW_STOCK_THRESHOLD);

        $lowStock = DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->leftJoin('inventory_stocks', fn ($j) => $j->on('inventory_stocks.variant_id', '=', 'product_variants.id')
                ->where('inventory_stocks.location_id', $inventory->defaultLocation()->id))
            ->whereNull('products.deleted_at')
            ->whereNull('product_variants.deleted_at')
            ->where('product_variants.track_inventory', true)
            ->whereRaw('COALESCE(inventory_stocks.on_hand, 0) <= COALESCE(product_variants.low_stock_threshold, ?)', [$threshold])
            ->count();

        return [
            'pending_payment' => Order::query()->where('status', OrderStatus::Pending)->count(),
            'awaiting_fulfillment' => Order::query()
                ->whereIn('status', [OrderStatus::Pending, OrderStatus::Processing])
                ->whereIn('fulfillment_status', [FulfillmentStatus::Unfulfilled, FulfillmentStatus::Packed])
                ->where(fn ($q) => $q->where('payment_status', PaymentStatus::Paid)->orWhere('payment_method_code', 'cash_on_delivery'))
                ->count(),
            'low_stock' => $lowStock,
        ];
    }
}

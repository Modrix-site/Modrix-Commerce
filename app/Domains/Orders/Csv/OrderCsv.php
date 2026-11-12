<?php

namespace App\Domains\Orders\Csv;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use App\Domains\Orders\Models\Shipment;
use Illuminate\Database\Eloquent\Builder;

/**
 * One row per order for spreadsheets and accounting tools. Cells that start
 * with a formula character are prefixed with an apostrophe (CSV injection).
 */
final class OrderCsv
{
    public const MAX_ROWS = 10000;

    public const COLUMNS = [
        'number', 'placed_at', 'customer', 'email', 'phone', 'country',
        'status', 'payment_status', 'fulfillment_status', 'currency',
        'subtotal', 'discount', 'shipping', 'tax', 'total', 'paid', 'refunded', 'balance_due',
        'payment_method', 'shipping_method', 'carrier', 'tracking_number', 'coupon', 'payment_fee',
    ];

    /**
     * Writes the header and up to MAX_ROWS orders to `$out`.
     *
     * @param  resource  $out
     * @param  Builder<Order>  $orders
     */
    public function write($out, Builder $orders, string $timezone): void
    {
        fwrite($out, "\u{FEFF}"); // UTF-8 BOM so spreadsheet apps detect the encoding
        fputcsv($out, self::COLUMNS, escape: '');

        $written = 0;
        $orders->with(['addresses', 'shipments'])->chunk(500, function ($chunk) use ($out, $timezone, &$written) {
            foreach ($chunk as $order) {
                if ($written >= self::MAX_ROWS) {
                    return false;
                }

                fputcsv($out, array_map(self::safe(...), $this->row($order, $timezone)), escape: '');
                $written++;
            }

            return true;
        });
    }

    /**
     * @return list<string>
     */
    private function row(Order $order, string $timezone): array
    {
        /** @var OrderAddress|null $address */
        $address = $order->addresses->firstWhere('type', 'billing') ?? $order->addresses->firstWhere('type', 'shipping');
        /** @var Shipment|null $shipment */
        $shipment = $order->shipments->last();
        $money = fn (string $attribute) => $order->money($attribute)->toDisplayDecimal();

        return [
            (string) $order->number,
            $order->placed_at->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
            $address ? trim($address->first_name.' '.$address->last_name) : '',
            $order->email,
            (string) $order->phone,
            $address->country_code ?? '',
            (string) $order->display_status,
            $order->payment_status->value,
            $order->fulfillment_status->value,
            $order->currency,
            $money('subtotal'),
            $money('discount_total'),
            $money('shipping_total'),
            $money('tax_total'),
            $money('grand_total'),
            $money('paid_total'),
            $money('refunded_total'),
            $order->balanceDue()->toDisplayDecimal(),
            $order->payment_method_name,
            (string) $order->shipping_method_name,
            (string) $shipment?->carrier,
            (string) $shipment?->tracking_number,
            (string) $order->coupon_code,
            $money('payment_fee_total'),
        ];
    }

    private static function safe(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }
}

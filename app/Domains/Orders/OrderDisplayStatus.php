<?php

namespace App\Domains\Orders;

use App\Domains\Orders\Enums\DisplayStatus;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;

/**
 * Derives the single headline status from the order, payment and
 * fulfilment tracks. The first matching rule wins.
 */
final class OrderDisplayStatus
{
    public static function for(Order $order): DisplayStatus
    {
        return self::derive($order->status, $order->payment_status, $order->fulfillment_status, $order);
    }

    /**
     * What the customer sees: holds show the status from before the hold,
     * and warehouse steps show as processing.
     */
    public static function forCustomer(Order $order): DisplayStatus
    {
        $status = $order->status === OrderStatus::OnHold
            ? (OrderStatus::tryFrom((string) $order->hold_previous_status) ?? OrderStatus::Processing)
            : $order->status;

        $display = self::derive($status, $order->payment_status, $order->fulfillment_status, $order);

        return $display->isInternal() ? DisplayStatus::Processing : $display;
    }

    private static function derive(OrderStatus $status, PaymentStatus $payment, FulfillmentStatus $fulfillment, ?Order $order = null): DisplayStatus
    {
        if ($status === OrderStatus::Cancelled) {
            return DisplayStatus::Cancelled;
        }
        if ($payment === PaymentStatus::Refunded) {
            return DisplayStatus::Refunded;
        }
        if ($fulfillment === FulfillmentStatus::Returned) {
            return DisplayStatus::Returned;
        }
        if ($payment === PaymentStatus::PartiallyRefunded) {
            return DisplayStatus::PartiallyRefunded;
        }
        if ($status === OrderStatus::OnHold) {
            return DisplayStatus::OnHold;
        }

        // Store Pickup lifecycle wiring
        if ($order !== null) {
            $isPickupMethod = $order->shipping_method_code === 'store_pickup'
                || str_starts_with((string) $order->shipping_method_code, 'pickup_');

            $pickup = null;
            if ($order->relationLoaded('pickup')) {
                $pickup = $order->pickup;
            } elseif ($isPickupMethod && \Illuminate\Support\Facades\Schema::hasTable('order_pickups')) {
                $pickup = \Illuminate\Support\Facades\DB::table('order_pickups')
                    ->where('order_id', $order->id)
                    ->first();
            }

            if ($pickup !== null) {
                $pickupStatus = is_object($pickup) ? $pickup->status : ($pickup['status'] ?? null);
                if ($pickupStatus === 'picked_up' || $status === OrderStatus::Completed || $fulfillment === FulfillmentStatus::Delivered) {
                    return DisplayStatus::PickedUp;
                }
                if ($pickupStatus === 'ready_for_pickup') {
                    return DisplayStatus::ReadyForPickup;
                }
            }
        }

        return match (true) {
            $fulfillment === FulfillmentStatus::DeliveryFailed => DisplayStatus::DeliveryFailed,
            $fulfillment === FulfillmentStatus::Delivered, $status === OrderStatus::Completed => DisplayStatus::Delivered,
            $fulfillment !== FulfillmentStatus::Unfulfilled => DisplayStatus::from($fulfillment->value),
            $payment === PaymentStatus::PartiallyPaid => DisplayStatus::PartiallyPaid,
            $payment === PaymentStatus::Failed => DisplayStatus::PaymentFailed,
            $status === OrderStatus::PendingPayment => DisplayStatus::PendingPayment,
            $status === OrderStatus::Pending => DisplayStatus::Pending,
            $status === OrderStatus::Confirmed => DisplayStatus::Confirmed,
            default => DisplayStatus::Processing,
        };
    }
}

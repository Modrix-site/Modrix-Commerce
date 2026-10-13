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
        return self::derive($order->status, $order->payment_status, $order->fulfillment_status);
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

        $display = self::derive($status, $order->payment_status, $order->fulfillment_status);

        return $display->isInternal() ? DisplayStatus::Processing : $display;
    }

    private static function derive(OrderStatus $status, PaymentStatus $payment, FulfillmentStatus $fulfillment): DisplayStatus
    {
        return match (true) {
            $status === OrderStatus::Cancelled => DisplayStatus::Cancelled,
            $payment === PaymentStatus::Refunded => DisplayStatus::Refunded,
            $fulfillment === FulfillmentStatus::Returned => DisplayStatus::Returned,
            $payment === PaymentStatus::PartiallyRefunded => DisplayStatus::PartiallyRefunded,
            $status === OrderStatus::OnHold => DisplayStatus::OnHold,
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

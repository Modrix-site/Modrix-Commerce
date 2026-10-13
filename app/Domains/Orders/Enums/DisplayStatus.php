<?php

namespace App\Domains\Orders\Enums;

/**
 * The one status people see for an order, derived from the order, payment
 * and fulfilment tracks by OrderDisplayStatus and stored on the order.
 */
enum DisplayStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentFailed = 'payment_failed';
    case PartiallyPaid = 'partially_paid';
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case OnHold = 'on_hold';
    case Processing = 'processing';
    case Picking = 'picking';
    case Packing = 'packing';
    case Packed = 'packed';
    case ReadyForDispatch = 'ready_for_dispatch';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case Cancelled = 'cancelled';
    case Returned = 'returned';
    case Refunded = 'refunded';
    case PartiallyRefunded = 'partially_refunded';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::PaymentFailed => 'Payment failed',
            self::PartiallyPaid => 'Partially paid',
            self::Pending => 'Pending',
            self::Confirmed => 'Confirmed',
            self::OnHold => 'On hold',
            self::Processing => 'Processing',
            self::Picking => 'Picking',
            self::Packing => 'Packing',
            self::Packed => 'Packed',
            self::ReadyForDispatch => 'Ready for dispatch',
            self::Shipped => 'Shipped',
            self::InTransit => 'In transit',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::DeliveryFailed => 'Delivery failed',
            self::Cancelled => 'Cancelled',
            self::Returned => 'Returned',
            self::Refunded => 'Refunded',
            self::PartiallyRefunded => 'Partially refunded',
        };
    }

    /** States only staff see (holds and warehouse steps). */
    public function isInternal(): bool
    {
        return in_array($this, [self::OnHold, self::Picking, self::Packing, self::Packed, self::ReadyForDispatch], true);
    }
}

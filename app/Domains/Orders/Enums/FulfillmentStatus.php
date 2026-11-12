<?php

namespace App\Domains\Orders\Enums;

/**
 * Where the goods are. The main path runs forward (steps may be skipped);
 * delivery failed and returned are side states.
 */
enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Picking = 'picking';
    case Packing = 'packing';
    case Packed = 'packed';
    case ReadyForDispatch = 'ready_for_dispatch';
    case Shipped = 'shipped';
    case InTransit = 'in_transit';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case Returned = 'returned';

    public function label(): string
    {
        return match ($this) {
            self::Unfulfilled => 'Unfulfilled',
            self::Picking => 'Picking',
            self::Packing => 'Packing',
            self::Packed => 'Packed',
            self::ReadyForDispatch => 'Ready for dispatch',
            self::Shipped => 'Shipped',
            self::InTransit => 'In transit',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::DeliveryFailed => 'Delivery failed',
            self::Returned => 'Returned',
        };
    }

    /**
     * Position on the main path (unfulfilled 0 … delivered 8). A failed
     * delivery sits after shipped; a return comes after everything.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Unfulfilled => 0,
            self::Picking => 1,
            self::Packing => 2,
            self::Packed => 3,
            self::ReadyForDispatch => 4,
            self::Shipped => 5,
            self::InTransit => 6,
            self::OutForDelivery => 7,
            self::DeliveryFailed => 7,
            self::Delivered => 8,
            self::Returned => 9,
        };
    }

    /** Steps before the parcel leaves (warehouse work). */
    public function isBeforeShipping(): bool
    {
        return $this->rank() < self::Shipped->rank();
    }

    /** With the carrier: shipped, in transit, out for delivery or a failed attempt. */
    public function isWithCarrier(): bool
    {
        return in_array($this, [self::Shipped, self::InTransit, self::OutForDelivery, self::DeliveryFailed], true);
    }

    /**
     * @return list<self> the warehouse steps, in order
     */
    public static function preShipSteps(): array
    {
        return [self::Picking, self::Packing, self::Packed, self::ReadyForDispatch];
    }
}

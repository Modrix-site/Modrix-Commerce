<?php

namespace App\Domains\Orders\Enums;

enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Unfulfilled => 'Unfulfilled',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
        };
    }
}

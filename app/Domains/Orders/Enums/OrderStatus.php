<?php

namespace App\Domains\Orders\Enums;

/**
 * Overall order lifecycle. Payment and fulfilment have their own states;
 * transitions are only performed by OrderWorkflow.
 */
enum OrderStatus: string
{
    case Pending = 'pending';       // placed, awaiting payment
    case Processing = 'processing'; // paid (or pay-on-delivery shipped), being fulfilled
    case Completed = 'completed';   // paid and delivered
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending payment',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function isFinal(): bool
    {
        return $this === self::Completed || $this === self::Cancelled;
    }
}

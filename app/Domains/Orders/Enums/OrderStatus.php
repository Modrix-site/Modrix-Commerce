<?php

namespace App\Domains\Orders\Enums;

/**
 * Overall order lifecycle. Payment and fulfilment have their own states;
 * transitions are only performed by OrderWorkflow. The status shown to
 * people is derived from all three (see OrderDisplayStatus).
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment'; // placed, awaiting payment
    case Pending = 'pending';                // received, awaiting the store's review (optional)
    case Confirmed = 'confirmed';            // accepted: paid, or pay-on-delivery
    case OnHold = 'on_hold';                 // paused by staff
    case Processing = 'processing';          // being fulfilled
    case Completed = 'completed';            // paid and delivered
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending payment',
            self::Pending => 'Pending review',
            self::Confirmed => 'Confirmed',
            self::OnHold => 'On hold',
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

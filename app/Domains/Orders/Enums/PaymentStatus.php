<?php

namespace App\Domains\Orders\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Failed = 'failed'; // the latest attempt failed and nothing has been paid
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Failed => 'Payment failed',
            self::Paid => 'Paid',
            self::PartiallyRefunded => 'Partially refunded',
            self::Refunded => 'Refunded',
        };
    }

    /** Nothing has been paid yet (a failed attempt can be retried). */
    public function isOutstanding(): bool
    {
        return $this === self::Unpaid || $this === self::Failed;
    }
}

<?php

namespace App\Domains\Inventory;

enum MovementReason: string
{
    case Initial = 'initial';
    case Adjustment = 'adjustment';
    case Received = 'received';
    case Damaged = 'damaged';
    case Sale = 'sale';
    case Cancellation = 'cancellation';
    case Restock = 'restock';
    case Import = 'import';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Initial stock',
            self::Adjustment => 'Manual adjustment',
            self::Received => 'Stock received',
            self::Damaged => 'Damaged / lost',
            self::Sale => 'Sold',
            self::Cancellation => 'Order cancelled',
            self::Restock => 'Refund restock',
            self::Import => 'CSV import',
        };
    }

    /**
     * Reasons staff may choose for a manual adjustment.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return [self::Adjustment, self::Received, self::Damaged];
    }
}

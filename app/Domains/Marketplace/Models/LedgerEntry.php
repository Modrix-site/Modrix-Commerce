<?php

namespace App\Domains\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only seller accounting: sales and refunds (gross, platform
 * commission, net owed) and payouts (negative net).
 *
 * @property int $id
 * @property int $publisher_id
 * @property string $type
 * @property int|null $order_item_id
 * @property int|null $payout_id
 * @property string $currency
 * @property string $gross
 * @property string $commission
 * @property string $net
 * @property string $description
 * @property Carbon $created_at
 */
class LedgerEntry extends Model
{
    public const SALE = 'sale';

    public const REFUND = 'refund';

    public const PAYOUT = 'payout';

    public const UPDATED_AT = null;

    protected $table = 'marketplace_ledger_entries';

    protected $guarded = ['*'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are append-only.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'gross' => 'decimal:4', 'commission' => 'decimal:4', 'net' => 'decimal:4'];
    }
}

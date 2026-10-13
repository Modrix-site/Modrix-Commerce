<?php

namespace App\Domains\Marketplace\Models;

use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A payment from the platform to a seller, recorded by the owner after the
 * transfer (payouts are made outside the application).
 *
 * @property int $id
 * @property int $publisher_id
 * @property string $currency
 * @property string $amount
 * @property string $reference
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon $paid_at
 */
class Payout extends Model
{
    protected $table = 'marketplace_payouts';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['paid_at' => 'datetime', 'amount' => 'decimal:4'];
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }
}

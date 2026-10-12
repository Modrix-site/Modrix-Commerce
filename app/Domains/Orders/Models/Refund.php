<?php

namespace App\Domains\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Record of money returned to the customer. For manual payment methods the
 * refund is made outside the platform and recorded here for reconciliation.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $payment_id
 * @property string $amount
 * @property string $currency
 * @property string $reason
 * @property string $status
 * @property string|null $provider_reference
 * @property int|null $actor_id
 * @property Carbon|null $created_at
 */
class Refund extends Model
{
    public const STATUS_COMPLETED = 'completed';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    /**
     * @return HasMany<RefundItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }
}

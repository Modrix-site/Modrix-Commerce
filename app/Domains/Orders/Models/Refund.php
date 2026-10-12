<?php

namespace App\Domains\Orders\Models;

use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Record of money returned to the customer. For manual payment methods the
 * refund is made outside the platform and recorded here for reconciliation.
 * Gateway refunds start `pending` until the provider confirms them, and
 * become `failed` if the provider reports a failure.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $payment_id
 * @property string $amount
 * @property string $currency
 * @property string $reason
 * @property string $status
 * @property string|null $provider_reference
 * @property string|null $failure_reason
 * @property int|null $actor_id
 * @property Carbon|null $created_at
 */
class Refund extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:4'];
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    /**
     * @return HasMany<RefundItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }
}

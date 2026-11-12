<?php

namespace App\Domains\Payments\Models;

use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment for an order. Never contains card data.
 *
 * Manual payments are recorded as `paid` by staff. Gateway payments start as
 * `pending` (a hosted payment session) and only become `paid` from a verified
 * webhook or a server-side retrieval of the session (ADR-027).
 *
 * @property int $id
 * @property int $order_id
 * @property string $method_code
 * @property string $provider
 * @property string|null $provider_reference
 * @property string|null $provider_payment_id
 * @property string|null $checkout_url
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property int|null $recorded_by
 * @property string|null $note
 * @property string|null $failure_reason
 * @property Carbon|null $paid_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property-read Order $order
 */
class Payment extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    /** The provider reported an amount or currency that does not match; needs staff review. */
    public const STATUS_MISMATCH = 'mismatch';

    /** Statuses in which the customer may still complete the payment. */
    public const OPEN = [self::STATUS_PENDING, self::STATUS_PROCESSING];

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'paid_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function money(): Money
    {
        return Money::of($this->amount, $this->currency);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    /**
     * The hosted payment page can still be used by the customer.
     */
    public function isResumable(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->checkout_url !== null
            && $this->expires_at !== null
            && $this->expires_at->isAfter(now()->addMinutes(2));
    }
}

<?php

namespace App\Domains\Marketplace\Models;

use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A purchase on the marketplace. Separate from merchant store orders.
 *
 * @property int $id
 * @property string|null $number
 * @property int $user_id
 * @property string $email
 * @property string $status
 * @property string $currency
 * @property string $total
 * @property string|null $stripe_session_id
 * @property string|null $stripe_payment_intent
 * @property Carbon|null $paid_at
 * @property Carbon|null $refunded_at
 * @property Carbon|null $created_at
 * @property-read User $user
 */
class MarketplaceOrder extends Model
{
    public const PENDING = 'pending';

    public const PAID = 'paid';

    public const REFUNDED = 'refunded';

    public const CANCELLED = 'cancelled';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['total' => 'decimal:4', 'paid_at' => 'datetime', 'refunded_at' => 'datetime'];
    }

    public function money(): Money
    {
        return Money::of($this->total, $this->currency);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<MarketplaceOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(MarketplaceOrderItem::class, 'order_id');
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}

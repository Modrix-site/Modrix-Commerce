<?php

namespace EcoModular\CartAbandonmentRecovery\Models;

use App\Domains\Cart\Models\Cart;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $cart_id
 * @property int|null $user_id
 * @property string $email
 * @property string|null $customer_name
 * @property string|null $phone
 * @property string $recovery_token
 * @property string $currency
 * @property string $subtotal
 * @property int $items_count
 * @property array<int, array<string, mixed>> $items_snapshot
 * @property string $status
 * @property string|null $applied_coupon_code
 * @property Carbon $abandoned_at
 * @property Carbon|null $recovered_at
 * @property int|null $recovered_order_id
 * @property string|null $recovery_revenue
 * @property int $emails_sent_count
 * @property Carbon|null $last_email_sent_at
 * @property Carbon|null $next_email_scheduled_at
 * @property Carbon|null $clicked_at
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Cart|null $cart
 * @property-read User|null $user
 * @property-read Order|null $recoveredOrder
 */
class AbandonedCart extends Model
{
    public const STATUS_ABANDONED = 'abandoned';

    public const STATUS_IN_RECOVERY = 'in_recovery';

    public const STATUS_RECOVERED = 'recovered';

    public const STATUS_LOST = 'lost';

    public const STATUS_UNSUBSCRIBED = 'unsubscribed';

    protected $table = 'abandoned_carts';

    protected $guarded = ['id'];

    protected $casts = [
        'items_snapshot' => 'array',
        'abandoned_at' => 'datetime',
        'recovered_at' => 'datetime',
        'last_email_sent_at' => 'datetime',
        'next_email_scheduled_at' => 'datetime',
        'clicked_at' => 'datetime',
        'items_count' => 'integer',
        'emails_sent_count' => 'integer',
    ];

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function recoveredOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'recovered_order_id');
    }

    /**
     * @return HasMany<AbandonedCartEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(AbandonedCartEmail::class, 'abandoned_cart_id')->orderBy('step');
    }

    public function formattedSubtotal(): string
    {
        return Money::of($this->subtotal, $this->currency)->format();
    }

    public function recoveryUrl(): string
    {
        return route('modrixcommerce.cart-abandonment-recovery.recover', ['token' => $this->recovery_token]);
    }

    public function unsubscribeUrl(): string
    {
        return route('modrixcommerce.cart-abandonment-recovery.unsubscribe', ['token' => $this->recovery_token]);
    }

    public function isRecovered(): bool
    {
        return $this->status === self::STATUS_RECOVERED;
    }

    public function markAsRecovered(Order $order): void
    {
        $this->update([
            'status' => self::STATUS_RECOVERED,
            'recovered_at' => now(),
            'recovered_order_id' => $order->id,
            'recovery_revenue' => $order->grand_total ?? $this->subtotal,
            'next_email_scheduled_at' => null,
        ]);
    }

    public function markAsLost(): void
    {
        $this->update([
            'status' => self::STATUS_LOST,
            'next_email_scheduled_at' => null,
        ]);
    }

    public function recordClick(): void
    {
        $this->update([
            'clicked_at' => now(),
            'status' => $this->status === self::STATUS_ABANDONED ? self::STATUS_IN_RECOVERY : $this->status,
        ]);
    }
}

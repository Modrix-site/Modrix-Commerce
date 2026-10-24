<?php

namespace Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $package_id
 * @property string $amount
 * @property string $currency
 * @property string|null $stripe_session
 * @property string|null $stripe_payment_intent
 * @property string $status pending|paid|expired|refunded
 * @property int|null $license_id
 */
class Order extends Model
{
    protected $fillable = ['user_id', 'package_id', 'amount', 'currency', 'stripe_session', 'stripe_payment_intent', 'status', 'license_id', 'paid_at'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}

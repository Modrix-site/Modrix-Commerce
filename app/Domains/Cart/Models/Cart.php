<?php

namespace App\Domains\Cart\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $token
 * @property int|null $user_id
 * @property string $currency
 * @property string|null $coupon_code
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Cart extends Model
{
    // Every attribute is set explicitly by CartManager; nothing is mass assignable.
    protected $guarded = ['*'];

    /**
     * @return HasMany<CartItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

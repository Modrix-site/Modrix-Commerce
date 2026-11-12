<?php

namespace App\Domains\Customers\Models;

use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Commerce profile of a buyer. Linked to a user account, or a guest (user_id null).
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property bool $accepts_marketing
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['email', 'first_name', 'last_name', 'phone', 'accepts_marketing'])]
class Customer extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['accepts_marketing' => 'boolean'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<CustomerAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default_shipping')->orderBy('id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function fullName(): string
    {
        return trim(($this->first_name ?? '').' '.($this->last_name ?? '')) ?: $this->email;
    }
}

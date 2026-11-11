<?php

namespace EcoModular\StorePickup\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Retail store or branch pickup location.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $address_line1
 * @property string|null $address_line2
 * @property string $city
 * @property string|null $region
 * @property string|null $postal_code
 * @property string $country_code
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $pickup_hours
 * @property string|null $instructions
 * @property string|null $prep_time
 * @property string $fee
 * @property bool $is_active
 * @property int $order
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class PickupBranch extends Model
{
    protected $table = 'pickup_branches';

    protected $fillable = [
        'name',
        'code',
        'address_line1',
        'address_line2',
        'city',
        'region',
        'postal_code',
        'country_code',
        'phone',
        'email',
        'pickup_hours',
        'instructions',
        'prep_time',
        'fee',
        'is_active',
        'order',
    ];

    protected $casts = [
        'fee' => 'decimal:4',
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('order')->orderBy('name');
    }

    public function orderPickups(): HasMany
    {
        return $this->hasMany(OrderPickup::class, 'pickup_branch_id');
    }

    public function fullAddress(): string
    {
        $parts = array_filter([
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->region,
            $this->postal_code,
            $this->country_code,
        ]);

        return implode(', ', $parts);
    }

    public function toPresentable(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'address_line1' => $this->address_line1,
            'address_line2' => $this->address_line2,
            'city' => $this->city,
            'region' => $this->region,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'full_address' => $this->fullAddress(),
            'phone' => $this->phone,
            'email' => $this->email,
            'pickup_hours' => $this->pickup_hours ?: 'Normal store hours',
            'instructions' => $this->instructions,
            'prep_time' => $this->prep_time ?: 'Ready for pickup in 2-4 hours',
            'fee' => (float) $this->fee,
            'is_active' => $this->is_active,
            'order' => $this->order,
        ];
    }
}

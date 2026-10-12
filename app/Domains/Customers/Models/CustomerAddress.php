<?php

namespace App\Domains\Customers\Models;

use App\Core\Settings\Countries;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $first_name
 * @property string $last_name
 * @property string|null $company
 * @property string $line1
 * @property string|null $line2
 * @property string $city
 * @property string|null $region
 * @property string|null $postal_code
 * @property string $country_code
 * @property string|null $phone
 * @property bool $is_default_shipping
 * @property bool $is_default_billing
 */
#[Fillable(['first_name', 'last_name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code', 'country_code', 'phone', 'is_default_shipping', 'is_default_billing'])]
class CustomerAddress extends Model
{
    public const FIELDS = ['first_name', 'last_name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code', 'country_code', 'phone'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default_shipping' => 'boolean', 'is_default_billing' => 'boolean'];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toPresentable(): array
    {
        return [
            ...$this->only(['id', ...self::FIELDS, 'is_default_shipping', 'is_default_billing']),
            'country_name' => Countries::name($this->country_code),
        ];
    }
}

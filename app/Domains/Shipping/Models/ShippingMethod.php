<?php

namespace App\Domains\Shipping\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A merchant-configured flat-rate method with an optional free-shipping threshold.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property string $rate
 * @property string $currency
 * @property string|null $free_over
 * @property list<string>|null $countries
 * @property bool $is_active
 * @property list<array{country_code: string, region: string, rate: string, free_over: string|null}>|null $regional_rates
 * @property bool $regions_only
 * @property string|null $delivery_estimate
 * @property int $position
 */
#[Fillable(['code', 'name', 'description', 'rate', 'currency', 'free_over', 'countries', 'is_active', 'position', 'regional_rates', 'regions_only', 'delivery_estimate'])]
class ShippingMethod extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'free_over' => 'decimal:4',
            'countries' => 'array',
            'regional_rates' => 'array',
            'regions_only' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function shipsTo(string $countryCode): bool
    {
        return $this->countries === null || $this->countries === [] || in_array(strtoupper($countryCode), $this->countries, true);
    }
}

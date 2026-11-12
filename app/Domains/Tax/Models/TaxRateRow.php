<?php

namespace App\Domains\Tax\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Row of the merchant's tax rate table.
 *
 * @property int $id
 * @property string $name
 * @property string $country_code
 * @property string|null $region
 * @property string $rate
 * @property bool $is_active
 */
#[Fillable(['name', 'country_code', 'region', 'rate', 'is_active'])]
class TaxRateRow extends Model
{
    protected $table = 'tax_rates';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['rate' => 'decimal:4', 'is_active' => 'boolean'];
    }
}

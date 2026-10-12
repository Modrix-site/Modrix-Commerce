<?php

namespace App\Domains\Orders\Models;

use App\Core\Settings\Countries;
use Illuminate\Database\Eloquent\Model;

/**
 * Address snapshot taken at checkout.
 *
 * @property int $id
 * @property int $order_id
 * @property string $type
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
 */
class OrderAddress extends Model
{
    public const SHIPPING = 'shipping';

    public const BILLING = 'billing';

    public $timestamps = false;

    protected $guarded = ['*'];

    /**
     * @return array<string, mixed>
     */
    public function toPresentable(): array
    {
        return [
            ...$this->only(['type', 'first_name', 'last_name', 'company', 'line1', 'line2', 'city', 'region', 'postal_code', 'country_code', 'phone']),
            'country_name' => Countries::name($this->country_code),
        ];
    }
}

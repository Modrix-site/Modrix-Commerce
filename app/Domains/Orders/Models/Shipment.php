<?php

namespace App\Domains\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $order_id
 * @property string|null $carrier
 * @property string|null $tracking_number
 * @property string|null $tracking_url
 * @property int|null $actor_id
 * @property Carbon $shipped_at
 * @property Carbon|null $delivered_at
 */
class Shipment extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }
}

<?php

namespace App\Domains\Orders\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $refund_id
 * @property int $order_item_id
 * @property int $quantity
 * @property bool $restocked
 */
class RefundItem extends Model
{
    public $timestamps = false;

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'restocked' => 'boolean'];
    }
}

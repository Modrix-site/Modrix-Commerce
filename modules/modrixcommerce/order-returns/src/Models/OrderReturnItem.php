<?php

namespace EcoModular\OrderReturns\Models;

use App\Domains\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_return_request_id
 * @property int $order_item_id
 * @property int $quantity
 */
class OrderReturnItem extends Model
{
    protected $table = 'order_return_items';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<OrderReturnRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(OrderReturnRequest::class, 'order_return_request_id');
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}

<?php

namespace EcoModular\DeliverySlots\Models;

use App\Domains\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scheduled delivery details for an order.
 */
class OrderDeliverySchedule extends Model
{
    protected $table = 'order_delivery_schedules';

    protected $guarded = [];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(DeliverySlot::class, 'delivery_slot_id');
    }
}

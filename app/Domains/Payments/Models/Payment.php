<?php

namespace App\Domains\Payments\Models;

use App\Domains\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A payment received for an order. Never contains card data.
 *
 * @property int $id
 * @property int $order_id
 * @property string $method_code
 * @property string $provider
 * @property string|null $provider_reference
 * @property string $amount
 * @property string $currency
 * @property string $status
 * @property int|null $recorded_by
 * @property string|null $note
 * @property Carbon|null $paid_at
 */
class Payment extends Model
{
    public const STATUS_PAID = 'paid';

    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:4', 'paid_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

<?php

namespace App\Domains\Orders\Models;

use App\Domains\Pricing\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot of a purchased line (except restock bookkeeping).
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $product_id
 * @property int|null $variant_id
 * @property string $sku
 * @property string $product_name
 * @property string|null $variant_name
 * @property int $quantity
 * @property string $unit_price
 * @property string $subtotal
 * @property string $discount_total
 * @property string $tax_rate
 * @property string $tax_total
 * @property string $total
 * @property int $restocked_quantity
 */
class OrderItem extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'restocked_quantity' => 'integer',
            'unit_price' => 'decimal:4',
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'tax_rate' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'total' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function money(string $attribute, string $currency): Money
    {
        return Money::of((string) $this->getAttribute($attribute), $currency);
    }
}

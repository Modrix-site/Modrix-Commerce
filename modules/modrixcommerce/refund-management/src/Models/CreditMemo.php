<?php

namespace EcoModular\RefundManagement\Models;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\Refund;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable credit memo snapshot issued against a refund.
 *
 * @property int $id
 * @property int $refund_id
 * @property int $order_id
 * @property string $number
 * @property int $sequence
 * @property string $currency
 * @property string $subtotal
 * @property string $tax_total
 * @property string $shipping_refund
 * @property string $adjustment_amount
 * @property string $grand_total
 * @property array<string, mixed> $seller
 * @property array<string, mixed> $buyer
 * @property list<array<string, mixed>> $lines
 * @property array<string, mixed> $totals
 * @property string|null $notes
 * @property Carbon $issued_at
 * @property int|null $issued_by
 */
class CreditMemo extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'subtotal' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'shipping_refund' => 'decimal:4',
            'adjustment_amount' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'seller' => 'array',
            'buyer' => 'array',
            'lines' => 'array',
            'totals' => 'array',
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Refund, $this>
     */
    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function money(string $attribute): Money
    {
        return Money::of((string) $this->getAttribute($attribute), $this->currency);
    }
}

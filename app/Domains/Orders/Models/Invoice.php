<?php

namespace App\Domains\Orders\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * An issued invoice: a frozen snapshot of the order, numbered from the
 * gap-free invoice sequence. Issued by OrderDocuments only; never changed.
 *
 * @property int $id
 * @property int $order_id
 * @property string $number
 * @property int $sequence
 * @property Carbon $issued_at
 * @property string $currency
 * @property array{name: string, email: string|null, address: string|null, tax_id: string|null} $seller
 * @property array{name: string, email: string, address: array<string, mixed>|null} $buyer
 * @property list<array{name: string, variant: string|null, sku: string, quantity: int, unit_price: string, tax_rate: string, tax: string, discount: string, total: string}> $lines
 * @property array{subtotal: string, discount_total: string, shipping_total: string, tax_total: string, payment_fee_total?: string, grand_total: string, prices_include_tax: bool, shipping_method: string|null, payment_method: string} $totals
 * @property string|null $notes
 * @property int|null $issued_by
 * @property-read Order $order
 */
class Invoice extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'seller' => 'array',
            'buyer' => 'array',
            'lines' => 'array',
            'totals' => 'array',
            'sequence' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Issued invoices cannot be changed.'));
        static::deleting(fn () => throw new LogicException('Issued invoices cannot be deleted.'));
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
}

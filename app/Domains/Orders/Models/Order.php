<?php

namespace App\Domains\Orders\Models;

use App\Domains\Customers\Models\Customer;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\OrderDisplayStatus;
use App\Domains\Payments\Models\Payment;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $number
 * @property int|null $customer_id
 * @property int|null $user_id
 * @property string $email
 * @property string|null $phone
 * @property string $currency
 * @property OrderStatus $status
 * @property string|null $hold_previous_status
 * @property string|null $display_status
 * @property PaymentStatus $payment_status
 * @property FulfillmentStatus $fulfillment_status
 * @property string $subtotal
 * @property string $discount_total
 * @property string $shipping_total
 * @property string $shipping_tax_total
 * @property string $tax_total
 * @property string $grand_total
 * @property string $paid_total
 * @property string $refunded_total
 * @property bool $prices_include_tax
 * @property string|null $coupon_code
 * @property string|null $shipping_method_code
 * @property string|null $shipping_method_name
 * @property string $payment_method_code
 * @property string $payment_method_name
 * @property string|null $customer_note
 * @property string|null $idempotency_key
 * @property Carbon $placed_at
 * @property Carbon|null $paid_at
 * @property Carbon|null $shipped_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property Carbon|null $created_at
 * @property-read Customer|null $customer
 * @property-read Invoice|null $invoice
 */
class Order extends Model
{
    // Orders are created and changed only by PlaceOrder and OrderWorkflow.
    protected $guarded = ['*'];

    protected static function booted(): void
    {
        // The headline status is stored for filtering and kept in step with every write.
        static::saving(function (Order $order) {
            $order->display_status = OrderDisplayStatus::for($order)->value;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'subtotal' => 'decimal:4',
            'discount_total' => 'decimal:4',
            'shipping_total' => 'decimal:4',
            'shipping_tax_total' => 'decimal:4',
            'tax_total' => 'decimal:4',
            'grand_total' => 'decimal:4',
            'paid_total' => 'decimal:4',
            'refunded_total' => 'decimal:4',
            'prices_include_tax' => 'boolean',
            'placed_at' => 'datetime',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    /**
     * @return HasMany<OrderAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    /**
     * @return HasOne<OrderAddress, $this>
     */
    public function shippingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', OrderAddress::SHIPPING);
    }

    /**
     * @return HasOne<OrderAddress, $this>
     */
    public function billingAddress(): HasOne
    {
        return $this->hasOne(OrderAddress::class)->where('type', OrderAddress::BILLING);
    }

    /**
     * @return HasOne<Invoice, $this>
     */
    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * @return HasMany<OrderEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('id');
    }

    /**
     * @return HasMany<Refund, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class)->orderBy('id');
    }

    /**
     * @return HasMany<Shipment, $this>
     */
    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function money(string $attribute): Money
    {
        return Money::of((string) $this->getAttribute($attribute), $this->currency);
    }

    /**
     * Amount that can still be refunded.
     */
    public function refundable(): Money
    {
        return $this->money('paid_total')->minus($this->money('refunded_total'))->nonNegative();
    }

    public function getRouteKeyName(): string
    {
        return 'number';
    }
}

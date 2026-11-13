<?php

namespace EcoModular\MultiVendor\Models;

use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketplaceVendorOrder extends Model
{
    public const PAYOUT_PENDING = 'pending';
    public const PAYOUT_CLEARED = 'cleared';
    public const PAYOUT_PAID_OUT = 'paid_out';
    public const PAYOUT_REFUNDED = 'refunded';

    protected $table = 'marketplace_vendor_orders';

    protected $fillable = [
        'vendor_id',
        'order_id',
        'order_item_id',
        'product_id',
        'order_number',
        'product_name',
        'quantity',
        'currency',
        'gross_amount',
        'commission_rate',
        'commission_amount',
        'vendor_earning',
        'fulfillment_status',
        'payout_status',
        'payout_id',
        'tracking_number',
    ];

    protected function casts(): array
    {
        return [
            'vendor_id' => 'integer',
            'order_id' => 'integer',
            'order_item_id' => 'integer',
            'product_id' => 'integer',
            'quantity' => 'integer',
            'gross_amount' => 'decimal:4',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:4',
            'vendor_earning' => 'decimal:4',
            'payout_id' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(MarketplaceVendor::class, 'vendor_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class, 'order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(MarketplaceVendorPayout::class, 'payout_id');
    }
}

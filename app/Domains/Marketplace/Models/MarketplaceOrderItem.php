<?php

namespace App\Domains\Marketplace\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Line-item snapshot of a marketplace purchase.
 *
 * @property int $id
 * @property int $order_id
 * @property int $product_id
 * @property string $product_name
 * @property string $product_type
 * @property string $unit_price
 * @property int|null $license_id
 * @property-read MarketplaceProduct $product
 * @property-read MarketplaceOrder $order
 * @property-read License|null $license
 */
class MarketplaceOrderItem extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['unit_price' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<MarketplaceProduct, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(MarketplaceProduct::class, 'product_id')->withTrashed();
    }

    /**
     * @return BelongsTo<MarketplaceOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(MarketplaceOrder::class, 'order_id');
    }

    /**
     * @return BelongsTo<License, $this>
     */
    public function license(): BelongsTo
    {
        return $this->belongsTo(License::class);
    }
}

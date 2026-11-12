<?php

namespace App\Domains\Cart\Models;

use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A cart line holds only the variant and quantity. Prices are never stored as
 * authoritative values: they are recalculated from the catalog on every read.
 *
 * @property int $id
 * @property int $cart_id
 * @property int $variant_id
 * @property int $quantity
 * @property string|null $seen_unit_price
 * @property-read ProductVariant $variant
 */
class CartItem extends Model
{
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['quantity' => 'integer', 'seen_unit_price' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<Cart, $this>
     */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id')->withTrashed();
    }
}

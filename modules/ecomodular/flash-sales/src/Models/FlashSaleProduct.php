<?php

namespace EcoModular\FlashSales\Models;

use App\Domains\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A product in a flash sale, with an optional discount of its own.
 *
 * @property int $id
 * @property int $flash_sale_id
 * @property int $product_id
 * @property string|null $discount_type
 * @property string|null $discount_value
 * @property-read FlashSale $sale
 * @property-read Product $product
 */
class FlashSaleProduct extends Model
{
    public $timestamps = false;

    protected $fillable = ['flash_sale_id', 'product_id', 'discount_type', 'discount_value'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['discount_value' => 'decimal:4'];
    }

    /**
     * @return BelongsTo<FlashSale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(FlashSale::class, 'flash_sale_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** The discount that applies: the product's override, else the sale's. */
    public function effectiveType(): string
    {
        return $this->discount_type ?? $this->sale->discount_type;
    }

    public function effectiveValue(): string
    {
        return $this->discount_value ?? $this->sale->discount_value;
    }
}

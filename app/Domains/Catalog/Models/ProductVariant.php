<?php

namespace App\Domains\Catalog\Models;

use App\Domains\Inventory\Models\InventoryStock;
use App\Domains\Pricing\Money;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $name
 * @property int $position
 * @property bool $is_default
 * @property bool $is_active
 * @property string|null $weight
 * @property string|null $cost
 * @property string|null $length
 * @property string|null $width
 * @property string|null $height
 * @property string|null $barcode
 * @property bool $track_inventory
 * @property bool $allow_backorder
 * @property int|null $low_stock_threshold
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Product $product
 */
#[Fillable(['sku', 'name', 'position', 'is_default', 'is_active', 'weight', 'cost', 'length', 'width', 'height', 'barcode', 'track_inventory', 'allow_backorder', 'low_stock_threshold'])]
#[UseFactory(ProductVariantFactory::class)]
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cost' => 'decimal:4',
            'length' => 'decimal:2',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
            'allow_backorder' => 'boolean',
            'low_stock_threshold' => 'integer',
            'position' => 'integer',
            'weight' => 'decimal:3',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * @return HasMany<ProductPrice, $this>
     */
    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class, 'variant_id');
    }

    /**
     * @return BelongsToMany<ProductAttributeValue, $this>
     */
    public function options(): BelongsToMany
    {
        return $this->belongsToMany(ProductAttributeValue::class, 'product_variant_options', 'variant_id', 'attribute_value_id');
    }

    /**
     * @return HasMany<InventoryStock, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(InventoryStock::class, 'variant_id');
    }

    public function priceIn(string $currency): ?Money
    {
        $price = $this->prices->firstWhere('currency', strtoupper($currency));

        return $price?->money();
    }

    public function compareAtPriceIn(string $currency): ?Money
    {
        $price = $this->prices->firstWhere('currency', strtoupper($currency));

        return $price?->compareAtMoney();
    }

    /**
     * Total on-hand quantity across locations (null when inventory is not tracked).
     */
    public function onHand(): ?int
    {
        if (! $this->track_inventory) {
            return null;
        }

        return (int) $this->stocks->sum('on_hand');
    }

    public function displayName(): string
    {
        $productName = $this->product->name;

        return $this->name ? "{$productName} — {$this->name}" : $productName;
    }
}

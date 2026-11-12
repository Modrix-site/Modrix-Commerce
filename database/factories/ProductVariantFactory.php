<?php

namespace Database\Factories;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'sku' => 'SKU-'.Str::upper(Str::random(8)),
            'position' => 0,
            'is_default' => true,
            'is_active' => true,
            'track_inventory' => true,
            'allow_backorder' => false,
        ];
    }
}

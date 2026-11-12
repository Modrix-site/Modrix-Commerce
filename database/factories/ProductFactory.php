<?php

namespace Database\Factories;

use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductType;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\MovementReason;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $name = Str::title(implode(' ', (array) fake()->unique()->words(3)));

        return [
            'type' => ProductType::Simple,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'status' => ProductStatus::Published,
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => ProductStatus::Draft, 'published_at' => null]);
    }

    /**
     * A single default variant with a price and (optionally) stock.
     *
     * @param  array<string, mixed>  $variantAttributes
     */
    public function withVariant(string $price = '20.00', ?int $stock = 10, string $currency = 'USD', array $variantAttributes = []): static
    {
        return $this->afterCreating(function (Product $product) use ($price, $stock, $currency, $variantAttributes) {
            $variant = ProductVariant::factory()->for($product)->create(array_merge([
                'is_default' => true,
                'track_inventory' => $stock !== null,
            ], $variantAttributes));

            $variant->prices()->create(['currency' => $currency, 'amount' => $price]);

            if ($stock !== null && $stock > 0) {
                app(InventoryService::class)->adjust($variant, $stock, MovementReason::Initial);
            }
        });
    }
}

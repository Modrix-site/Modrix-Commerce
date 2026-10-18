<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductType;
use App\Domains\Catalog\Events\ProductCreated;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductAttributeValue;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductTag;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\ProductRelations;
use App\Domains\Catalog\Slugger;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\MovementReason;
use App\Domains\Pricing\Money;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates or updates a product with its variants, prices and categories in
 * one transaction. Prices are stored in the given (store default) currency.
 *
 * Variant invariants:
 *  - a simple product has exactly one variant and no options;
 *  - every variant of a variable product has exactly one value for each
 *    selected option attribute, and option combinations are unique;
 *  - variants omitted from an update are soft-deleted (order history keeps them).
 *
 * @phpstan-type VariantInput array{id?: int|null, sku: string, name?: string|null, image_id?: int|null, price: string, compare_at_price?: string|null, weight?: string|null, cost?: string|null, length?: string|null, width?: string|null, height?: string|null, barcode?: string|null, track_inventory?: bool, allow_backorder?: bool, low_stock_threshold?: int|null, is_active?: bool, option_value_ids?: list<int>, initial_stock?: int|null}
 * @phpstan-type ProductInput array{type: string, name: string, slug?: string|null, status: string, brand_id?: int|null, is_featured?: bool, is_digital?: bool, show_buy_button?: bool|null, tags?: list<string>, related_ids?: list<int>, upsell_ids?: list<int>, published_at?: string|null, short_description?: string|null, description?: string|null, meta_title?: string|null, meta_description?: string|null, category_ids?: list<int>, option_attribute_ids?: list<int>, variants: list<VariantInput>}
 */
final class SaveProduct
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly AuditLogger $audit,
        private readonly ProductRelations $relations,
    ) {}

    /**
     * @param  ProductInput  $data
     */
    public function handle(?Product $product, array $data, string $currency, ?User $actor = null): Product
    {
        $type = ProductType::from($data['type']);
        $optionAttributeIds = array_values(array_unique(array_map('intval', $data['option_attribute_ids'] ?? [])));
        $this->validateVariantShape($type, $optionAttributeIds, $data['variants']);

        return DB::transaction(function () use ($product, $data, $type, $optionAttributeIds, $currency, $actor) {
            $isNew = $product === null;
            $product ??= new Product;

            $status = ProductStatus::from($data['status']);
            $slug = $this->resolveSlug($product, $data);

            $product->fill([
                'type' => $type,
                'name' => $data['name'],
                'slug' => $slug,
                'status' => $status,
                'published_at' => match (true) {
                    filled($data['published_at'] ?? null) => Carbon::parse((string) $data['published_at']),
                    $status === ProductStatus::Published => $product->published_at ?? now(),
                    default => $product->published_at,
                },
                'short_description' => $data['short_description'] ?? null,
                'description' => $data['description'] ?? null,
                'meta_title' => $data['meta_title'] ?? null,
                'meta_description' => $data['meta_description'] ?? null,
                'brand_id' => isset($data['brand_id']) ? (int) $data['brand_id'] : null,
                'is_featured' => (bool) ($data['is_featured'] ?? false),
                'is_digital' => (bool) ($data['is_digital'] ?? false),
                'show_buy_button' => array_key_exists('show_buy_button', $data) && $data['show_buy_button'] !== null
                    ? (bool) $data['show_buy_button']
                    : null,
            ])->save();

            $product->categories()->sync(array_map('intval', $data['category_ids'] ?? []));

            if (array_key_exists('tags', $data)) {
                $this->syncTags($product, $data['tags']);
            }

            if (array_key_exists('related_ids', $data) || array_key_exists('upsell_ids', $data)) {
                $this->relations->sync($product, array_map('intval', $data['related_ids'] ?? []), array_map('intval', $data['upsell_ids'] ?? []));
            }

            $this->syncVariants($product, $data['variants'], $optionAttributeIds, $currency, $actor);

            $this->audit->record($isNew ? 'product.created' : 'product.updated', $product, [
                'name' => $product->name,
                'status' => $product->status->value,
                'skus' => array_column($data['variants'], 'sku'),
            ], $actor);

            if ($isNew) {
                DB::afterCommit(fn () => ProductCreated::dispatch($product->id));
            }

            return $product->load('variants.prices', 'categories');
        });
    }

    /**
     * @param  list<int>  $optionAttributeIds
     * @param  list<VariantInput>  $variants
     */
    private function validateVariantShape(ProductType $type, array $optionAttributeIds, array $variants): void
    {
        if ($variants === []) {
            throw ValidationException::withMessages(['variants' => 'At least one variant is required.']);
        }

        if ($type === ProductType::Simple) {
            if (count($variants) !== 1) {
                throw ValidationException::withMessages(['variants' => 'A simple product has exactly one variant.']);
            }

            return;
        }

        if ($optionAttributeIds === [] || count($optionAttributeIds) > 3) {
            throw ValidationException::withMessages(['option_attribute_ids' => 'Choose between one and three options (for example Size and Color).']);
        }

        $values = ProductAttributeValue::query()
            ->whereIn('id', collect($variants)->flatMap(fn ($v) => $v['option_value_ids'] ?? [])->unique()->all())
            ->get()
            ->keyBy('id');

        $combinations = [];

        foreach ($variants as $index => $variant) {
            $attributeIds = [];

            foreach ($variant['option_value_ids'] ?? [] as $valueId) {
                $value = $values->get($valueId);

                if ($value === null || ! in_array($value->attribute_id, $optionAttributeIds, true)) {
                    throw ValidationException::withMessages(["variants.{$index}.option_value_ids" => 'Invalid option value.']);
                }
                $attributeIds[] = $value->attribute_id;
            }

            sort($attributeIds);
            $expected = $optionAttributeIds;
            sort($expected);

            if ($attributeIds !== $expected) {
                throw ValidationException::withMessages(["variants.{$index}.option_value_ids" => 'Choose exactly one value for each option.']);
            }

            $key = collect($variant['option_value_ids'] ?? [])->sort()->implode('-');

            if (isset($combinations[$key])) {
                throw ValidationException::withMessages(["variants.{$index}.option_value_ids" => 'Two variants have the same options.']);
            }
            $combinations[$key] = true;
        }
    }

    /**
     * @param  list<string>  $tags
     */
    private function syncTags(Product $product, array $tags): void
    {
        $normalised = array_values(array_unique(array_filter(array_map(ProductTag::normalise(...), $tags), fn (string $t) => $t !== '')));
        sort($normalised);

        if (count($normalised) > ProductTag::MAX_PER_PRODUCT) {
            throw ValidationException::withMessages(['tags' => 'A product can have at most '.ProductTag::MAX_PER_PRODUCT.' tags.']);
        }

        $product->tags()->delete();
        $product->tags()->insert(array_map(fn (string $tag) => ['product_id' => $product->id, 'tag' => $tag], $normalised));
    }

    /**
     * @param  ProductInput  $data
     */
    private function resolveSlug(Product $product, array $data): string
    {
        if (filled($data['slug'] ?? null)) {
            $slug = Str::slug((string) $data['slug']);
            $taken = Product::withTrashed()->where('slug', $slug)
                ->when($product->exists, fn ($q) => $q->whereKeyNot($product->id))
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages(['slug' => 'This URL slug is already used by another product.']);
            }

            return $slug;
        }

        return $product->exists ? $product->slug : Slugger::unique(Product::class, $data['name']);
    }

    /**
     * @param  list<VariantInput>  $rows
     * @param  list<int>  $optionAttributeIds
     */
    private function syncVariants(Product $product, array $rows, array $optionAttributeIds, string $currency, ?User $actor): void
    {
        $existing = $product->variants()->get()->keyBy('id');
        $keptIds = [];
        $optionNames = ProductAttributeValue::query()->pluck('value', 'id');

        foreach ($rows as $position => $row) {
            $variantId = isset($row['id']) ? (int) $row['id'] : null;

            if ($variantId !== null && ! $existing->has($variantId)) {
                throw ValidationException::withMessages(["variants.{$position}.id" => 'This variant does not belong to the product.']);
            }

            $variant = ($variantId !== null ? $existing->get($variantId) : null) ?? new ProductVariant;
            $isNewVariant = ! $variant->exists;

            if (isset($row['image_id']) && ! ProductImage::query()->whereKey($row['image_id'])->where('product_id', $product->id)->exists()) {
                throw ValidationException::withMessages(["variants.{$position}.image_id" => 'Choose an image from this product.']);
            }

            $optionValueIds = array_map('intval', $row['option_value_ids'] ?? []);
            $name = filled($row['name'] ?? null)
                ? (string) $row['name']
                : ($optionValueIds === [] ? null : collect($optionValueIds)->map(fn ($id) => $optionNames[$id] ?? '')->implode(' / '));

            $variant->product_id = $product->id;
            if (array_key_exists('image_id', $row)) {
                $variant->image_id = $row['image_id'];
            }
            $variant->fill([
                'sku' => Str::upper(trim($row['sku'])),
                'name' => $name,
                'position' => $position,
                'is_default' => $position === 0,
                'is_active' => $row['is_active'] ?? true,
                'weight' => $row['weight'] ?? null,
                'cost' => filled($row['cost'] ?? null) ? Money::of((string) $row['cost'], $currency)->toDecimal() : null,
                'length' => $row['length'] ?? null,
                'width' => $row['width'] ?? null,
                'height' => $row['height'] ?? null,
                'barcode' => $row['barcode'] ?? null,
                // Digital products do not track stock unless asked to.
                'track_inventory' => $row['track_inventory'] ?? ! $product->is_digital,
                'allow_backorder' => $row['allow_backorder'] ?? false,
                'low_stock_threshold' => $row['low_stock_threshold'] ?? null,
            ])->save();

            $variant->options()->sync($optionAttributeIds === [] ? [] : $optionValueIds);

            $price = Money::of($row['price'], $currency);
            $compareAt = filled($row['compare_at_price'] ?? null) ? Money::of((string) $row['compare_at_price'], $currency) : null;

            if ($price->isNegative()) {
                throw ValidationException::withMessages(["variants.{$position}.price" => 'The price cannot be negative.']);
            }

            if ($compareAt !== null && ! $compareAt->greaterThan($price)) {
                throw ValidationException::withMessages(["variants.{$position}.compare_at_price" => 'The compare-at price must be higher than the price.']);
            }

            $variant->prices()->updateOrCreate(
                ['currency' => $currency],
                ['amount' => $price->toDecimal(), 'compare_at_amount' => $compareAt?->toDecimal()],
            );

            $initialStock = (int) ($row['initial_stock'] ?? 0);

            if ($isNewVariant && $initialStock > 0 && $variant->track_inventory) {
                $this->inventory->adjust($variant, $initialStock, MovementReason::Initial, $actor, 'Initial stock');
            }

            $keptIds[] = $variant->id;
        }

        foreach ($existing->except($keptIds) as $removed) {
            $removed->delete();
        }
    }
}

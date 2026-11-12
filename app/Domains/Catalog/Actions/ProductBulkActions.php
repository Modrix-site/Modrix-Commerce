<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Currencies;
use App\Domains\Catalog\BulkResult;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductPrice;
use App\Domains\Catalog\Models\ProductTag;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\MovementReason;
use App\Domains\Pricing\Money;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Changes many products at once (product list → bulk actions). Each action
 * runs in one transaction and is audited once with the product ids.
 */
final class ProductBulkActions
{
    public const ACTIONS = [
        'publish', 'draft', 'archive',
        'add_category', 'remove_category', 'set_brand', 'add_tags', 'remove_tags', 'feature', 'unfeature',
        'adjust_prices', 'set_stock', 'delete',
    ];

    /** Most products per action. */
    public const MAX = 200;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly InventoryService $inventory,
        private readonly DeleteProduct $delete,
    ) {}

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $input  the action's validated fields
     */
    public function run(string $action, array $ids, array $input, User $actor): BulkResult
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException("Unknown bulk action [{$action}].");
        }

        $result = new BulkResult;

        DB::transaction(function () use ($action, $ids, $input, $actor, $result) {
            $products = Product::query()->whereIn('id', $ids)->with(['variants.prices', 'tags'])->orderBy('id')->get();

            foreach ($products as $product) {
                $changed = $this->apply($action, $product, $input, $actor, $result);
                $result->updated += $changed ? 1 : 0;
            }

            $this->audit->record("product.bulk_{$action}", null, [
                'ids' => $products->pluck('id')->all(),
                'input' => $input,
                'updated' => $result->updated,
                'skipped' => array_column($result->skipped, 'id'),
            ], $actor);
        });

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return bool whether the product changed
     */
    private function apply(string $action, Product $product, array $input, User $actor, BulkResult $result): bool
    {
        switch ($action) {
            case 'publish':
                if (! $this->hasPricedVariant($product)) {
                    $result->skip($product->id, $product->name, 'no active variant with a price');

                    return false;
                }
                $product->forceFill(['status' => ProductStatus::Published, 'published_at' => $product->published_at ?? now()])->save();

                return true;
            case 'draft':
                $product->forceFill(['status' => ProductStatus::Draft])->save();

                return true;
            case 'archive':
                $product->forceFill(['status' => ProductStatus::Archived])->save();

                return true;
            case 'add_category':
                $product->categories()->syncWithoutDetaching([(int) $input['category_id']]);

                return true;
            case 'remove_category':
                $product->categories()->detach((int) $input['category_id']);

                return true;
            case 'set_brand':
                $product->forceFill(['brand_id' => $input['brand_id'] ?? null])->save();

                return true;
            case 'feature':
            case 'unfeature':
                $product->forceFill(['is_featured' => $action === 'feature'])->save();

                return true;
            case 'add_tags':
                return $this->addTags($product, $this->tags($input), $result);
            case 'remove_tags':
                $product->tags()->whereIn('tag', $this->tags($input))->delete();

                return true;
            case 'adjust_prices':
                return $this->adjustPrices($product, $input, $result);
            case 'set_stock':
                return $this->setStock($product, (int) $input['quantity'], $actor, $result);
            case 'delete':
                $this->delete->handle($product);

                return true;
        }

        return false;
    }

    private function hasPricedVariant(Product $product): bool
    {
        return $product->variants->contains(fn (ProductVariant $v) => $v->is_active && $v->prices->isNotEmpty());
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<string>
     */
    private function tags(array $input): array
    {
        $tags = is_array($input['tags'] ?? null) ? $input['tags'] : [];

        return array_values(array_unique(array_filter(array_map(fn ($t) => ProductTag::normalise((string) $t), $tags), fn (string $t) => $t !== '')));
    }

    /**
     * @param  list<string>  $tags
     */
    private function addTags(Product $product, array $tags, BulkResult $result): bool
    {
        $existing = $product->tags->pluck('tag')->all();
        $new = array_values(array_diff($tags, $existing));

        if (count($existing) + count($new) > ProductTag::MAX_PER_PRODUCT) {
            $result->skip($product->id, $product->name, 'would have more than '.ProductTag::MAX_PER_PRODUCT.' tags');

            return false;
        }

        foreach ($new as $tag) {
            $product->tags()->create(['tag' => $tag]);
        }

        return true;
    }

    /**
     * Every price of every variant, in every currency. Prices never go below
     * the currency's smallest amount; products where that happened are listed.
     *
     * @param  array<string, mixed>  $input
     */
    private function adjustPrices(Product $product, array $input, BulkResult $result): bool
    {
        $floored = false;
        $value = (string) $input['value'];

        foreach ($product->variants as $variant) {
            foreach ($variant->prices as $row) {
                /** @var ProductPrice $row */
                $price = Money::of($row->amount, $row->currency);
                $change = $input['mode'] === 'percent' ? $price->percentage($value) : Money::of($value, $row->currency);
                $new = ($input['direction'] === 'increase' ? $price->plus($change) : $price->minus($change))->rounded();
                $minimum = Money::of(BigDecimal::one()->withPointMovedLeft(Currencies::ALL[$row->currency]['minor_units'] ?? 2), $row->currency);

                if ($new->lessThan($minimum)) {
                    $new = $minimum;
                    $floored = true;
                }

                $row->forceFill(['amount' => $new->toDecimal()])->save();
            }
        }

        if ($floored) {
            $result->skip($product->id, $product->name, 'price floored at the lowest amount');
        }

        return true;
    }

    private function setStock(Product $product, int $quantity, User $actor, BulkResult $result): bool
    {
        /** @var Collection<int, ProductVariant> $tracked */
        $tracked = $product->is_digital ? collect() : $product->variants->filter(fn (ProductVariant $v) => $v->track_inventory);

        if ($tracked->isEmpty()) {
            $result->skip($product->id, $product->name, 'stock is not tracked');

            return false;
        }

        foreach ($tracked as $variant) {
            $this->inventory->setOnHand($variant, $quantity, MovementReason::Adjustment, $actor, 'Bulk stock update');
        }

        return true;
    }
}

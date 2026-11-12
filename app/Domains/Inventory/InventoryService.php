<?php

namespace App\Domains\Inventory;

use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Events\InventoryLow;
use App\Domains\Inventory\Exceptions\InsufficientStock;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\Models\InventoryStock;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only code path that changes stock levels.
 *
 * Every change locks the affected stock rows (SELECT … FOR UPDATE, in
 * ascending variant order to avoid deadlocks), writes the new quantity and an
 * immutable movement row in the same transaction. Version 1 uses the single
 * default location; the schema already supports more.
 */
final class InventoryService
{
    public const DEFAULT_LOW_STOCK_THRESHOLD = 5;

    public function __construct(private readonly SettingsRepository $settings) {}

    public function defaultLocation(): InventoryLocation
    {
        return InventoryLocation::query()->where('is_default', true)->firstOr(
            fn () => InventoryLocation::query()->firstOrCreate(['code' => 'main'], ['name' => 'Main warehouse', 'is_default' => true])
        );
    }

    public function lowStockThreshold(ProductVariant $variant): int
    {
        return $variant->low_stock_threshold
            ?? (int) $this->settings->get('inventory.low_stock_threshold', self::DEFAULT_LOW_STOCK_THRESHOLD);
    }

    /**
     * Change stock by a signed delta (manual adjustments, receipts, imports).
     * Manual changes may not take a non-backorder variant below zero.
     */
    public function adjust(ProductVariant $variant, int $delta, MovementReason $reason, ?User $actor = null, ?string $note = null, ?Model $reference = null): InventoryStock
    {
        if ($delta === 0) {
            throw new InvalidArgumentException('Adjustment quantity must not be zero.');
        }

        return DB::transaction(function () use ($variant, $delta, $reason, $actor, $note, $reference) {
            $stock = $this->lockStocks([$variant->id])->firstOrFail();

            $after = $stock->on_hand + $delta;

            if ($after < 0 && ! $variant->allow_backorder) {
                throw new InsufficientStock([[
                    'variant_id' => $variant->id,
                    'sku' => $variant->sku,
                    'requested' => -$delta,
                    'available' => $stock->on_hand,
                ]]);
            }

            $this->apply($variant, $stock, $delta, $reason, $actor, $note, $reference);

            return $stock;
        });
    }

    /**
     * Set an absolute on-hand quantity (stock count). Records the difference.
     */
    public function setOnHand(ProductVariant $variant, int $quantity, MovementReason $reason, ?User $actor = null, ?string $note = null): void
    {
        if ($quantity < 0) {
            throw new InvalidArgumentException('Quantity cannot be negative.');
        }

        DB::transaction(function () use ($variant, $quantity, $reason, $actor, $note) {
            $stock = $this->lockStocks([$variant->id])->firstOrFail();

            $delta = $quantity - $stock->on_hand;

            if ($delta !== 0) {
                $this->apply($variant, $stock, $delta, $reason, $actor, $note, null);
            }
        });
    }

    /**
     * Decrement stock for a sale. All-or-nothing: if any tracked,
     * non-backorder line lacks stock, nothing changes and the shortages are
     * reported together.
     *
     * @param  array<int, int>  $quantities  variant id => quantity
     */
    public function allocate(array $quantities, Model $reference, ?User $actor = null): void
    {
        $this->assertPositive($quantities);

        DB::transaction(function () use ($quantities, $reference, $actor) {
            $variants = ProductVariant::withTrashed()->whereKey(array_keys($quantities))->get()->keyBy('id');
            $tracked = array_filter($quantities, fn ($qty, $id) => (bool) $variants->get($id)?->track_inventory, ARRAY_FILTER_USE_BOTH);

            if ($tracked === []) {
                return;
            }

            $stocks = $this->lockStocks(array_keys($tracked))->keyBy('variant_id');
            $shortages = [];

            foreach ($tracked as $variantId => $quantity) {
                /** @var ProductVariant $variant */
                $variant = $variants->get($variantId);
                /** @var InventoryStock $stock */
                $stock = $stocks->get($variantId);

                if (! $variant->allow_backorder && $stock->on_hand < $quantity) {
                    $shortages[] = [
                        'variant_id' => $variantId,
                        'sku' => $variant->sku,
                        'requested' => $quantity,
                        'available' => max(0, $stock->on_hand),
                    ];
                }
            }

            if ($shortages !== []) {
                throw new InsufficientStock($shortages);
            }

            foreach ($tracked as $variantId => $quantity) {
                /** @var ProductVariant $variant */
                $variant = $variants->get($variantId);
                /** @var InventoryStock $stock */
                $stock = $stocks->get($variantId);
                $this->apply($variant, $stock, -$quantity, MovementReason::Sale, $actor, null, $reference);
            }
        });
    }

    /**
     * Return previously allocated stock (cancellation or refund restock).
     *
     * @param  array<int, int>  $quantities  variant id => quantity
     */
    public function release(array $quantities, MovementReason $reason, Model $reference, ?User $actor = null): void
    {
        $this->assertPositive($quantities);

        DB::transaction(function () use ($quantities, $reason, $reference, $actor) {
            $variants = ProductVariant::withTrashed()->whereKey(array_keys($quantities))->get()->keyBy('id');
            $tracked = array_filter($quantities, fn ($qty, $id) => (bool) $variants->get($id)?->track_inventory, ARRAY_FILTER_USE_BOTH);

            if ($tracked === []) {
                return;
            }

            $stocks = $this->lockStocks(array_keys($tracked))->keyBy('variant_id');

            foreach ($tracked as $variantId => $quantity) {
                /** @var ProductVariant $variant */
                $variant = $variants->get($variantId);
                /** @var InventoryStock $stock */
                $stock = $stocks->get($variantId);
                $this->apply($variant, $stock, $quantity, $reason, $actor, null, $reference);
            }
        });
    }

    /**
     * Ensure stock rows exist, then lock them in a deterministic order.
     *
     * @param  list<int>  $variantIds
     * @return Collection<int, InventoryStock>
     */
    private function lockStocks(array $variantIds): Collection
    {
        $locationId = $this->defaultLocation()->id;
        sort($variantIds);

        // insertOrIgnore relies on the (variant_id, location_id) unique index, so
        // concurrent first-time inserts cannot create duplicates.
        DB::table('inventory_stocks')->insertOrIgnore(array_map(fn (int $id) => [
            'variant_id' => $id,
            'location_id' => $locationId,
            'on_hand' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $variantIds));

        return InventoryStock::query()
            ->whereIn('variant_id', $variantIds)
            ->where('location_id', $locationId)
            ->orderBy('variant_id')
            ->lockForUpdate()
            ->get();
    }

    private function apply(ProductVariant $variant, InventoryStock $stock, int $delta, MovementReason $reason, ?User $actor, ?string $note, ?Model $reference): void
    {
        $before = $stock->on_hand;
        $after = $before + $delta;

        // on_hand is guarded against mass assignment; it is only ever written here.
        $stock->forceFill(['on_hand' => $after])->save();

        InventoryMovement::query()->create([
            'variant_id' => $variant->id,
            'location_id' => $stock->location_id,
            'quantity_change' => $delta,
            'quantity_after' => $after,
            'reason' => $reason,
            'reference_type' => $reference?->getMorphClass(),
            'reference_id' => $reference?->getKey(),
            'actor_id' => $actor?->id,
            'note' => $note,
        ]);

        $threshold = $this->lowStockThreshold($variant);

        if ($variant->track_inventory && $before > $threshold && $after <= $threshold) {
            DB::afterCommit(fn () => InventoryLow::dispatch($variant->id, $variant->sku, $after, $threshold));
        }
    }

    /**
     * @param  array<int, int>  $quantities
     */
    private function assertPositive(array $quantities): void
    {
        foreach ($quantities as $quantity) {
            if ($quantity <= 0) {
                throw new InvalidArgumentException('Quantities must be positive.');
            }
        }
    }
}

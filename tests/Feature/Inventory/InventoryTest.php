<?php

namespace Tests\Feature\Inventory;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Events\InventoryLow;
use App\Domains\Inventory\Exceptions\InsufficientStock;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\MovementReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use LogicException;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->inventory = app(InventoryService::class);
    }

    private function variant(int $stock = 10, array $attributes = []): ProductVariant
    {
        $product = Product::factory()->withVariant(stock: $stock, variantAttributes: $attributes)->create();

        return $product->variants()->firstOrFail();
    }

    private function onHand(ProductVariant $variant): int
    {
        return (int) $variant->stocks()->sum('on_hand');
    }

    public function test_allocation_is_all_or_nothing()
    {
        $a = $this->variant(5);
        $b = $this->variant(1);
        $order = $a->product; // any model works as a reference for this test

        try {
            $this->inventory->allocate([$a->id => 2, $b->id => 3], $order);
            $this->fail('Expected InsufficientStock');
        } catch (InsufficientStock $e) {
            $this->assertSame($b->sku, $e->shortages[0]['sku']);
            $this->assertSame(1, $e->shortages[0]['available']);
        }

        $this->assertSame(5, $this->onHand($a));
        $this->assertSame(1, $this->onHand($b));

        $this->inventory->allocate([$a->id => 2, $b->id => 1], $order);
        $this->assertSame(3, $this->onHand($a));
        $this->assertSame(0, $this->onHand($b));
        $this->assertDatabaseHas('inventory_movements', ['variant_id' => $b->id, 'quantity_change' => -1, 'quantity_after' => 0, 'reason' => 'sale']);
    }

    public function test_backorder_variants_may_go_negative_and_untracked_variants_are_ignored()
    {
        $backorder = $this->variant(1, ['allow_backorder' => true]);
        $untracked = $this->variant(0, ['track_inventory' => false]);

        $this->inventory->allocate([$backorder->id => 3, $untracked->id => 50], $backorder->product);

        $this->assertSame(-2, $this->onHand($backorder));
        $this->assertSame(0, InventoryMovement::query()->where('variant_id', $untracked->id)->count());
    }

    public function test_release_returns_stock()
    {
        $variant = $this->variant(2);
        $this->inventory->allocate([$variant->id => 2], $variant->product);
        $this->inventory->release([$variant->id => 2], MovementReason::Cancellation, $variant->product);

        $this->assertSame(2, $this->onHand($variant));
    }

    public function test_low_stock_event_fires_once_when_crossing_threshold()
    {
        Event::fake([InventoryLow::class]);
        $variant = $this->variant(8, ['low_stock_threshold' => 5]);

        $this->inventory->allocate([$variant->id => 2], $variant->product); // 6: above
        $this->inventory->allocate([$variant->id => 2], $variant->product); // 4: crosses
        $this->inventory->allocate([$variant->id => 1], $variant->product); // 3: already low

        Event::assertDispatchedTimes(InventoryLow::class, 1);
    }

    public function test_movements_are_immutable()
    {
        $variant = $this->variant(1);
        $movement = InventoryMovement::query()->where('variant_id', $variant->id)->firstOrFail();

        $this->expectException(LogicException::class);
        $movement->update(['quantity_change' => 999]);
    }

    public function test_admin_adjustments_and_permissions()
    {
        $variant = $this->variant(4);
        $manager = $this->userWithRole('catalog-manager');

        $this->actingAs($manager)
            ->post(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 6, 'reason' => 'received', 'note' => 'PO-7'])
            ->assertSessionHasNoErrors();
        $this->assertSame(10, $this->onHand($variant));

        $this->actingAs($manager)
            ->post(route('admin.inventory.adjust', $variant), ['mode' => 'remove', 'quantity' => 11, 'reason' => 'damaged'])
            ->assertSessionHasErrors('quantity');
        $this->assertSame(10, $this->onHand($variant));

        $this->actingAs($manager)
            ->post(route('admin.inventory.adjust', $variant), ['mode' => 'set', 'quantity' => 7, 'reason' => 'adjustment'])
            ->assertSessionHasNoErrors();
        $this->assertSame(7, $this->onHand($variant));
        $this->assertDatabaseHas('inventory_movements', ['variant_id' => $variant->id, 'quantity_change' => -3, 'reason' => 'adjustment', 'actor_id' => $manager->id]);

        // Sale is not a manual reason.
        $this->actingAs($manager)
            ->post(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 1, 'reason' => 'sale'])
            ->assertSessionHasErrors('reason');

        $this->actingAs($this->userWithRole('order-manager'))->get(route('admin.inventory.index'))->assertOk();
        $this->actingAs($this->userWithRole('order-manager'))
            ->post(route('admin.inventory.adjust', $variant), ['mode' => 'add', 'quantity' => 1, 'reason' => 'received'])
            ->assertForbidden();
        $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.inventory.index'))->assertForbidden();
    }

    public function test_low_stock_filter()
    {
        $this->variant(2);
        $this->variant(50);

        $this->actingAs($this->owner())
            ->get(route('admin.inventory.index', ['stock' => 'low']))
            ->assertInertia(fn ($page) => $page->has('variants.data', 1)->where('variants.data.0.on_hand', 2));
    }
}

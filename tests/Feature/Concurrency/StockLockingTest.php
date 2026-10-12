<?php

namespace Tests\Feature\Concurrency;

use App\Domains\Catalog\Models\Product;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\Models\InventoryStock;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Proves that stock allocation holds a row lock until its transaction ends,
 * so a concurrent checkout must wait (and then sees the reduced stock).
 * Requires MySQL: SQLite has no row-level locks. Runs in the CI MySQL job.
 */
#[Group('mysql')]
class StockLockingTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Row-locking semantics are only verifiable on MySQL.');
        }

        $this->installPlatform();
    }

    public function test_allocation_locks_the_stock_row_until_commit()
    {
        $variant = Product::factory()->withVariant('10.00', 1)->create()->variants()->firstOrFail();
        $stockId = InventoryStock::query()->where('variant_id', $variant->id)->value('id');

        // A second, independent connection to the same database.
        config(['database.connections.mysql_second' => config('database.connections.mysql')]);
        $second = DB::connection('mysql_second');

        DB::beginTransaction();
        app(InventoryService::class)->allocate([$variant->id => 1], $variant->product);

        try {
            $second->select('SELECT id FROM inventory_stocks WHERE id = ? FOR UPDATE NOWAIT', [$stockId]);
            $this->fail('Expected the stock row to be locked by the open allocation.');
        } catch (QueryException $e) {
            $this->assertStringContainsStringIgnoringCase('lock', $e->getMessage());
        } finally {
            DB::commit();
        }

        $this->assertSame(0, (int) $second->table('inventory_stocks')->where('id', $stockId)->value('on_hand'));
        $second->disconnect();
    }
}

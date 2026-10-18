<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Actions\SaveAttribute;
use App\Domains\Catalog\Actions\SaveProduct;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SqliteProductSaveTest extends TestCase
{
    public function test_disk_database_supports_nested_initial_stock_writes_and_rollbacks(): void
    {
        $path = tempnam(storage_path('framework/testing'), 'sqlite-stock-');
        $this->assertNotFalse($path);
        config(['database.connections.stock_test' => ['driver' => 'sqlite', 'database' => $path, 'prefix' => '', 'foreign_key_constraints' => true], 'database.default' => 'stock_test']);
        try {
            Artisan::call('migrate', ['--database' => 'stock_test', '--force' => true]);
            $this->installPlatform();
            $this->assertSame(2, (int) DB::selectOne('PRAGMA temp_store')->temp_store);
            $this->assertSame('delete', DB::selectOne('PRAGMA journal_mode')->journal_mode);
            $attribute = app(SaveAttribute::class)->handle(null, ['name' => 'Color', 'values' => [['value' => 'Blue'], ['value' => 'Red']]]);
            [$blue, $red] = $attribute->values->pluck('id')->all();
            $data = ['name' => 'Disk test phone', 'type' => 'variable', 'status' => 'published', 'option_attribute_ids' => [$attribute->id], 'variants' => [
                ['sku' => 'DISK-BLUE', 'price' => '10', 'option_value_ids' => [$blue], 'initial_stock' => 10],
                ['sku' => 'DISK-RED', 'price' => '12', 'option_value_ids' => [$red], 'initial_stock' => 12],
            ]];
            DB::beginTransaction();
            $product = app(SaveProduct::class)->handle(null, $data, 'USD');
            $this->assertSame(22, (int) DB::table('inventory_stocks')->sum('on_hand'));
            DB::rollBack();
            $this->assertDatabaseMissing('products', ['name' => 'Disk test phone']);
            $product = app(SaveProduct::class)->handle(null, $data, 'USD');
            $this->assertSame(2, $product->variants()->count());
            $this->assertSame(22, (int) DB::table('inventory_stocks')->sum('on_hand'));
            DB::disconnect('stock_test');
            $this->assertSame(2, (int) DB::connection('stock_test')->selectOne('PRAGMA temp_store')->temp_store);
            $this->assertSame(22, (int) DB::table('inventory_stocks')->sum('on_hand'));
            $this->assertSame('ok', DB::selectOne('PRAGMA quick_check')->quick_check);
        } finally {
            while (DB::connection('stock_test')->transactionLevel() > 0) {
                DB::rollBack();
            }
            DB::purge('stock_test');
            @unlink($path);
        }
    }
}

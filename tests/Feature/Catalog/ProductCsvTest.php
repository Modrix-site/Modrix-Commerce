<?php

namespace Tests\Feature\Catalog;

use App\Domains\Catalog\Csv\ProductCsv;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Http\Controllers\Admin\Catalog\ProductCsvController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class ProductCsvTest extends TestCase
{
    use CommerceFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    /**
     * @param  list<list<string>>  $rows
     */
    private function csv(array $rows, ?array $header = null): UploadedFile
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header ?? ['sku', 'product_name', 'price', 'on_hand', 'status'], escape: '');

        foreach ($rows as $row) {
            fputcsv($handle, $row, escape: '');
        }
        rewind($handle);

        return UploadedFile::fake()->createWithContent('products.csv', (string) stream_get_contents($handle));
    }

    private function import(User $user, UploadedFile $file)
    {
        return $this->actingAs($user)->post(route('admin.products.import'), ['file' => $file]);
    }

    public function test_export_lists_every_variant_with_price_and_stock()
    {
        $variant = $this->sellable('24.50', 7, ['sku' => 'TEE-001'], ['name' => 'Cotton Tee']);
        $category = Category::factory()->create(['slug' => 'shirts']);
        $variant->product->categories()->attach($category);

        $response = $this->actingAs($this->userWithRole('analyst'))->get(route('admin.products.export'));

        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $lines = array_map(fn ($l) => str_getcsv($l, escape: ''), array_filter(explode("\n", ltrim($response->streamedContent(), "\u{FEFF}"))));

        $this->assertSame(ProductCsv::COLUMNS, $lines[0]);
        $row = array_combine(ProductCsv::COLUMNS, $lines[1]);
        $this->assertSame('TEE-001', $row['sku']);
        $this->assertSame('Cotton Tee', $row['product_name']);
        $this->assertSame('24.50', $row['price']);
        $this->assertSame('7', $row['on_hand']);
        $this->assertSame('shirts', $row['categories']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.csv_exported']);
    }

    public function test_export_neutralises_spreadsheet_formulas()
    {
        $this->sellable(product: ['name' => '=HYPERLINK("http://evil.test")']);

        $content = $this->actingAs($this->owner())->get(route('admin.products.export'))->streamedContent();

        $this->assertStringContainsString("'=HYPERLINK", $content);
        $this->assertSame("'+1", ProductCsv::safeCell('+1'));
        $this->assertSame("'@SUM(A1)", ProductCsv::safeCell('@SUM(A1)'));
        $this->assertSame('Plain', ProductCsv::safeCell('Plain'));
    }

    public function test_export_requires_products_view()
    {
        $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.products.export'))->assertForbidden();
    }

    public function test_import_creates_new_products_and_updates_existing_skus()
    {
        $existing = $this->sellable('10.00', 5, ['sku' => 'OLD-1'], ['name' => 'Old name']);

        $this->import($this->userWithRole('catalog-manager'), $this->csv([
            ['old-1', 'New name', '12.00', '9', 'published'],
            ['NEW-1', 'Fresh product', '3.5', '4', ''],
        ]))->assertRedirect(route('admin.products.index'))->assertSessionMissing(ProductCsvController::SESSION_ERRORS);

        $existing->refresh()->load('prices', 'stocks', 'product');
        $this->assertSame('New name', $existing->product->name);
        $this->assertSame('12.00', $existing->priceIn('USD')?->toDisplayDecimal());
        $this->assertSame(9, $existing->onHand());
        $this->assertDatabaseHas('inventory_movements', ['variant_id' => $existing->id, 'quantity_change' => 4, 'reason' => 'import']);

        $new = ProductVariant::query()->where('sku', 'NEW-1')->with('product', 'prices')->firstOrFail();
        $this->assertSame('Fresh product', $new->product->name);
        $this->assertSame('draft', $new->product->status->value);
        $this->assertSame('3.50', $new->priceIn('USD')?->toDisplayDecimal());
        $this->assertDatabaseHas('audit_logs', ['action' => 'catalog.csv_imported']);
    }

    public function test_import_is_all_or_nothing_and_reports_row_errors()
    {
        $existing = $this->sellable('10.00', 5, ['sku' => 'OLD-1'], ['name' => 'Old name']);

        $this->import($this->owner(), $this->csv([
            ['OLD-1', 'Renamed', '11.00', '', ''],
            ['BAD SKU!', 'Nope', '1.00', '', ''],
            ['NEW-2', '', 'abc', '-3', 'sold'],
        ]))->assertRedirect(route('admin.products.index'))
            ->assertSessionHas(ProductCsvController::SESSION_ERRORS, function (array $errors) {
                $text = implode("\n", $errors);

                return str_contains($text, 'Row 3: SKU is missing')
                    && str_contains($text, 'Row 4: price must be')
                    && str_contains($text, 'Row 4: product_name is required')
                    && str_contains($text, 'Row 4: on_hand must be')
                    && str_contains($text, 'Row 4: status must be');
            });

        $this->assertSame('Old name', $existing->product->fresh()->name);
        $this->assertDatabaseMissing('product_variants', ['sku' => 'NEW-2']);
        $this->assertSame(1, Product::query()->count());
    }

    public function test_import_errors_are_shown_on_the_products_page()
    {
        $user = $this->owner();
        $this->import($user, $this->csv([['', 'x', '1', '', '']]));

        $this->actingAs($user)->get(route('admin.products.index'))
            ->assertInertia(fn (Assert $page) => $page->has('importErrors', 1));
    }

    public function test_import_rejects_unknown_columns_duplicates_and_existing_slugs()
    {
        $this->sellable(product: ['slug' => 'taken']);

        $this->import($this->owner(), $this->csv([['A-1']], ['sku', 'secret_column']))
            ->assertSessionHas(ProductCsvController::SESSION_ERRORS, ['Unknown columns: secret_column.']);

        $this->import($this->owner(), $this->csv([
            ['A-1', 'One', '1.00', 'taken'],
            ['A-1', 'Two', '1.00', ''],
        ], ['sku', 'product_name', 'price', 'product_slug']))
            ->assertSessionHas(ProductCsvController::SESSION_ERRORS, fn (array $e) => str_contains(implode(' ', $e), 'existing product')
                && str_contains(implode(' ', $e), 'appears more than once'));
    }

    public function test_stock_changes_require_inventory_manage()
    {
        $role = $this->roleWithPermissions('csv-editor', ['products.view', 'products.create', 'products.update']);
        $user = User::factory()->create();
        $user->roles()->attach($role->id, ['created_at' => now()]);
        $existing = $this->sellable('10.00', 5, ['sku' => 'OLD-1']);

        $this->import($user->fresh(), $this->csv([['OLD-1', '', '', '50', '']]))
            ->assertSessionHas(ProductCsvController::SESSION_ERRORS, fn (array $e) => str_contains($e[0], 'permission to change stock'));
        $this->assertSame(5, $existing->fresh()->onHand());

        $this->import($user->fresh(), $this->csv([['OLD-1', '', '15.00', '', '']]))
            ->assertSessionMissing(ProductCsvController::SESSION_ERRORS);
        $this->assertSame('15.00', $existing->fresh()->priceIn('USD')?->toDisplayDecimal());
    }

    public function test_import_requires_create_and_update_permissions()
    {
        $this->import($this->userWithRole('analyst'), $this->csv([['A-1', 'x', '1', '', '']]))->assertForbidden();
        $this->assertDatabaseMissing('product_variants', ['sku' => 'A-1']);
    }

    public function test_import_validates_the_upload()
    {
        $this->actingAs($this->owner())->post(route('admin.products.import'), [
            'file' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php'),
        ])->assertSessionHasErrors('file');
    }
}

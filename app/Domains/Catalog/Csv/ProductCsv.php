<?php

namespace App\Domains\Catalog\Csv;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductType;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Slugger;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\MovementReason;
use App\Domains\Pricing\Money;
use App\Models\User;
use Generator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use SplFileObject;

/**
 * Product/variant CSV export and import.
 *
 * Import is keyed by SKU and all-or-nothing: every row is validated first and
 * nothing is written if any row has an error. Existing SKUs are updated; a
 * new SKU creates a simple product. Adding variants to an existing product
 * via CSV is not supported in v1 (use the product editor).
 *
 * Exported cells that start with a formula character are prefixed with an
 * apostrophe so spreadsheets do not execute them (CSV injection).
 */
final class ProductCsv
{
    public const COLUMNS = [
        'sku', 'product_name', 'product_slug', 'status', 'variant_name', 'price', 'compare_at_price',
        'currency', 'on_hand', 'track_inventory', 'allow_backorder', 'weight', 'barcode', 'categories',
    ];

    public function __construct(
        private readonly InventoryService $inventory,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @param  list<int>|null  $productIds  only these products (bulk export), or all
     * @return Generator<int, list<string>>
     */
    public function export(string $currency, ?array $productIds = null): Generator
    {
        yield self::COLUMNS;

        $query = ProductVariant::query()
            ->whereHas('product')
            ->when($productIds !== null, fn ($q) => $q->whereIn('product_id', $productIds))
            ->with(['product.categories:id,slug', 'prices', 'stocks'])
            ->orderBy('product_id')
            ->orderBy('position');

        foreach ($query->lazyById(500) as $variant) {
            $price = $variant->priceIn($currency);
            $compare = $variant->compareAtPriceIn($currency);

            yield array_map([self::class, 'safeCell'], [
                $variant->sku,
                $variant->product->name,
                $variant->product->slug,
                $variant->product->status->value,
                (string) $variant->name,
                $price?->toDisplayDecimal() ?? '',
                $compare?->toDisplayDecimal() ?? '',
                $currency,
                $variant->track_inventory ? (string) $variant->onHand() : '',
                $variant->track_inventory ? '1' : '0',
                $variant->allow_backorder ? '1' : '0',
                (string) $variant->weight,
                (string) $variant->barcode,
                $variant->product->categories->pluck('slug')->implode('|'),
            ]);
        }
    }

    public static function safeCell(string $value): string
    {
        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    public function import(string $path, string $currency, User $actor, bool $canAdjustStock, int $maxRows): ImportResult
    {
        $rows = $this->read($path, $maxRows);

        if ($rows instanceof ImportResult) {
            return $rows;
        }

        $errors = [];
        $plans = [];
        $seenSkus = [];
        $existing = ProductVariant::withTrashed()->whereIn('sku', array_map(fn ($r) => Str::upper(trim($r['sku'] ?? '')), $rows))
            ->get()->keyBy(fn (ProductVariant $v) => Str::upper($v->sku));
        $categoryIds = Category::query()->pluck('id', 'slug');

        foreach ($rows as $line => $row) {
            [$plan, $rowErrors] = $this->validateRow($row, $currency, $existing, $categoryIds, $seenSkus, $canAdjustStock);

            foreach ($rowErrors as $message) {
                $errors[] = "Row {$line}: {$message}";
            }

            if ($plan !== null) {
                $plans[] = $plan;
            }
        }

        if ($errors !== []) {
            return ImportResult::failed($errors);
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($plans, $currency, $actor, &$created, &$updated) {
            foreach ($plans as $plan) {
                $plan['variant'] === null ? $created++ : $updated++;
                $this->apply($plan, $currency, $actor);
            }
        });

        $this->audit->record('catalog.csv_imported', null, ['created' => $created, 'updated' => $updated], $actor);

        return ImportResult::succeeded($created, $updated);
    }

    /**
     * @return array<int, array<string, string>>|ImportResult rows keyed by spreadsheet line number
     */
    private function read(string $path, int $maxRows): array|ImportResult
    {
        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD | SplFileObject::DROP_NEW_LINE);

        $header = null;
        $rows = [];

        foreach ($file as $index => $cells) {
            if (! is_array($cells) || $cells === [null]) {
                continue;
            }

            $cells = array_map(fn ($c) => trim((string) $c), $cells);

            if ($header === null) {
                $header = array_map(fn ($h) => Str::lower(ltrim($h, "\u{FEFF}")), $cells);
                $unknown = array_diff($header, self::COLUMNS);

                if (! in_array('sku', $header, true)) {
                    return ImportResult::failed(['The file must have a header row with a "sku" column.']);
                }

                if ($unknown !== []) {
                    return ImportResult::failed(['Unknown columns: '.implode(', ', $unknown).'.']);
                }

                continue;
            }

            if (count($rows) >= $maxRows) {
                return ImportResult::failed(["The file has more than {$maxRows} rows. Split it into smaller files."]);
            }

            $rows[(int) $index + 1] = array_combine($header, array_pad(array_slice($cells, 0, count($header)), count($header), ''));
        }

        return $header === null ? ImportResult::failed(['The file is empty.']) : $rows;
    }

    /**
     * @param  array<string, string>  $row
     * @param  Collection<string, ProductVariant>  $existing
     * @param  Collection<string, int>  $categoryIds
     * @param  array<string, true>  $seenSkus
     * @return array{0: array<string, mixed>|null, 1: list<string>}
     */
    private function validateRow(array $row, string $currency, $existing, $categoryIds, array &$seenSkus, bool $canAdjustStock): array
    {
        $errors = [];
        $sku = Str::upper($row['sku'] ?? '');

        if ($sku === '' || ! preg_match('/^[A-Z0-9._-]{1,64}$/', $sku)) {
            return [null, ['SKU is missing or contains invalid characters.']];
        }

        if (isset($seenSkus[$sku])) {
            return [null, ["SKU {$sku} appears more than once."]];
        }
        $seenSkus[$sku] = true;

        $variant = $existing->get($sku);

        if ($variant !== null && $variant->trashed()) {
            return [null, ["SKU {$sku} belongs to a deleted variant."]];
        }

        $money = '/^\d{1,13}(\.\d{1,4})?$/';

        foreach (['price', 'compare_at_price'] as $field) {
            if (($row[$field] ?? '') !== '' && ! preg_match($money, $row[$field])) {
                $errors[] = "{$field} must be a positive decimal number (e.g. 19.99).";
            }
        }

        if (($row['currency'] ?? '') !== '' && Str::upper($row['currency']) !== $currency) {
            $errors[] = "currency must be {$currency} (the store currency).";
        }

        if (($row['status'] ?? '') !== '' && ProductStatus::tryFrom($row['status']) === null) {
            $errors[] = 'status must be draft, published or archived.';
        }

        foreach (['track_inventory', 'allow_backorder'] as $flag) {
            if (($row[$flag] ?? '') !== '' && ! in_array($row[$flag], ['0', '1'], true)) {
                $errors[] = "{$flag} must be 0 or 1.";
            }
        }

        if (($row['on_hand'] ?? '') !== '') {
            if (! ctype_digit($row['on_hand'])) {
                $errors[] = 'on_hand must be a whole number of 0 or more.';
            } elseif (! $canAdjustStock) {
                $errors[] = 'You do not have permission to change stock; remove the on_hand column.';
            }
        }

        if (($row['weight'] ?? '') !== '' && ! is_numeric($row['weight'])) {
            $errors[] = 'weight must be a number.';
        }

        $categories = array_values(array_filter(explode('|', $row['categories'] ?? '')));
        $unknownCategories = array_diff($categories, $categoryIds->keys()->all());

        if ($unknownCategories !== []) {
            $errors[] = 'Unknown category slugs: '.implode(', ', $unknownCategories).'.';
        }

        if ($variant === null) {
            if (($row['product_name'] ?? '') === '') {
                $errors[] = 'product_name is required for a new SKU.';
            }

            if (($row['price'] ?? '') === '') {
                $errors[] = 'price is required for a new SKU.';
            }

            if (($row['product_slug'] ?? '') !== '' && Product::withTrashed()->where('slug', Str::slug($row['product_slug']))->exists()) {
                $errors[] = 'Adding a new variant to an existing product is not supported by CSV import; use the product editor.';
            }
        }

        $price = ($row['price'] ?? '') !== '' && $errors === [] ? Money::of($row['price'], $currency) : null;
        $compare = ($row['compare_at_price'] ?? '') !== '' && $errors === [] ? Money::of($row['compare_at_price'], $currency) : null;
        $effectivePrice = $price ?? $variant?->priceIn($currency);

        if ($compare !== null && $effectivePrice !== null && ! $compare->greaterThan($effectivePrice)) {
            $errors[] = 'compare_at_price must be higher than price.';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [[
            'sku' => $sku,
            'variant' => $variant,
            'row' => $row,
            'price' => $price,
            'compare' => $compare,
            'category_ids' => array_map(fn ($slug) => $categoryIds[$slug], $categories),
        ], []];
    }

    /**
     * @param  array<string, mixed>  $plan
     */
    private function apply(array $plan, string $currency, User $actor): void
    {
        /** @var array<string, string> $row */
        $row = $plan['row'];
        /** @var ProductVariant|null $variant */
        $variant = $plan['variant'];
        $has = fn (string $column) => array_key_exists($column, $row) && $row[$column] !== '';

        if ($variant === null) {
            $product = new Product;
            $product->fill([
                'type' => ProductType::Simple,
                'name' => $row['product_name'],
                'slug' => $has('product_slug') ? Str::slug($row['product_slug']) : Slugger::unique(Product::class, $row['product_name']),
                'status' => $status = $has('status') ? ProductStatus::from($row['status']) : ProductStatus::Draft,
                'published_at' => $status === ProductStatus::Published ? now() : null,
            ]);
            $product->save();

            $variant = new ProductVariant(['sku' => $plan['sku'], 'is_default' => true, 'position' => 0]);
            $variant->product_id = $product->id;
        } else {
            $product = $variant->product;

            if ($has('product_name')) {
                $product->name = $row['product_name'];
            }

            if ($has('status')) {
                $status = ProductStatus::from($row['status']);
                $product->fill([
                    'status' => $status,
                    'published_at' => $product->published_at ?? ($status === ProductStatus::Published ? now() : null),
                ]);
            }
            $product->save();
        }

        $variant->fill(array_filter([
            'name' => $has('variant_name') ? $row['variant_name'] : null,
            'track_inventory' => $has('track_inventory') ? $row['track_inventory'] === '1' : null,
            'allow_backorder' => $has('allow_backorder') ? $row['allow_backorder'] === '1' : null,
            'weight' => $has('weight') ? $row['weight'] : null,
            'barcode' => $has('barcode') ? $row['barcode'] : null,
        ], fn ($v) => $v !== null))->save();

        if ($plan['price'] !== null || $plan['compare'] !== null) {
            $current = $variant->prices()->where('currency', $currency)->first();
            $variant->prices()->updateOrCreate(['currency' => $currency], [
                'amount' => ($plan['price'] ?? $current?->money())?->toDecimal(),
                'compare_at_amount' => $plan['compare']?->toDecimal() ?? $current?->compare_at_amount,
            ]);
        }

        if ($plan['category_ids'] !== []) {
            $product->categories()->syncWithoutDetaching($plan['category_ids']);
        }

        if ($has('on_hand') && $variant->track_inventory) {
            $this->inventory->setOnHand($variant, (int) $row['on_hand'], MovementReason::Import, $actor, 'CSV import');
        }
    }
}

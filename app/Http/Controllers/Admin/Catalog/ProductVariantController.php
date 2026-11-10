<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\CommittedStock;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\Models\InventoryLocation;
use App\Domains\Inventory\MovementReason;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One variant of a product (price, inventory per location, shipping), with
 * the product's other variants alongside, like Shopify's variant page.
 * Option values are managed on the product, where variants are generated.
 */
class ProductVariantController extends Controller
{
    public function show(Product $product, ProductVariant $variant, StoreSettings $store, InventoryService $inventory, CommittedStock $committed): Response
    {
        abort_unless($variant->product_id === $product->id, 404);
        $product->load(['images' => fn ($q) => $q->orderBy('position'), 'variants' => fn ($q) => $q->orderBy('position'), 'variants.options.attribute']);
        $variant->load(['prices', 'options.attribute', 'stocks']);
        $currency = $store->currency();
        $price = $variant->prices->firstWhere('currency', $currency);
        $committedUnits = $committed->forVariants([$variant->id])[$variant->id] ?? 0;
        $default = $inventory->defaultLocation();

        // Committed units are counted at the default location, where orders take stock from.
        $locations = InventoryLocation::query()->orderBy('id')->get()->map(function (InventoryLocation $location) use ($variant, $default, $committedUnits) {
            $available = (int) ($variant->stocks->firstWhere('location_id', $location->id)?->on_hand ?? 0);
            $held = $location->id === $default->id ? $committedUnits : 0;

            return ['id' => $location->id, 'name' => $location->name, 'available' => $available, 'committed' => $held, 'on_hand' => $available + $held];
        })->values();

        return Inertia::render('admin/products/variant', [
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'status' => $product->status->value ?? (string) $product->status,
                'image_url' => $product->images->first()?->url(),
                'edit_url' => route('admin.products.edit', $product),
                'options' => $product->variants->flatMap(fn (ProductVariant $v) => $v->options)
                    ->groupBy(fn ($value) => $value->attribute->name)
                    ->map(fn ($values, $name) => ['name' => $name, 'values' => $values->pluck('value')->unique()->values()])
                    ->values(),
                'variants' => $product->variants->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'name' => $v->name ?: 'Default',
                    'sku' => $v->sku,
                    'image_url' => $product->images->firstWhere('id', $v->image_id)?->url(),
                    'options' => $v->options->mapWithKeys(fn ($value) => [$value->attribute->name => $value->value]),
                    'url' => route('admin.products.variants.show', [$product, $v]),
                ])->values(),
                'images' => $product->images->map(fn (ProductImage $image) => ['id' => $image->id, 'url' => $image->url(), 'alt' => $image->alt])->values(),
            ],
            'variant' => [
                'id' => $variant->id,
                'name' => $variant->name ?: $product->name,
                'options' => $variant->options->map(fn ($value) => ['name' => $value->attribute->name, 'value' => $value->value])->values(),
                'image_id' => $variant->image_id,
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'price' => $price?->amount !== null ? Money::of((string) $price->amount, $currency)->toDisplayDecimal() : '',
                'compare_at_price' => $price?->compare_at_amount !== null ? Money::of((string) $price->compare_at_amount, $currency)->toDisplayDecimal() : '',
                'cost' => $variant->cost !== null ? Money::of((string) $variant->cost, $currency)->toDisplayDecimal() : '',
                'track_inventory' => $variant->track_inventory,
                'allow_backorder' => $variant->allow_backorder,
                'low_stock_threshold' => $variant->low_stock_threshold,
                'weight' => $variant->weight,
                'length' => $variant->length,
                'width' => $variant->width,
                'height' => $variant->height,
                'is_active' => $variant->is_active,
            ],
            'locations' => $locations,
            'currency' => $currency,
            'units' => ['weight' => $store->toArray()['weight_unit'], 'dimension' => $store->toArray()['dimension_unit']],
            'urls' => [
                'update' => route('admin.products.variants.update', [$product, $variant]),
                'adjust' => route('admin.inventory.adjust', $variant),
                'history' => route('admin.inventory.movements', ['sku' => $variant->sku]),
            ],
            'reasons' => array_map(fn (MovementReason $r) => ['value' => $r->value, 'label' => $r->label()], MovementReason::manual()),
        ]);
    }

    public function update(Request $request, Product $product, ProductVariant $variant, StoreSettings $store, AuditLogger $audit): RedirectResponse
    {
        abort_unless($variant->product_id === $product->id, 404);
        $money = 'regex:'.SaveProductRequest::MONEY_PATTERN;
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9._-]+$/'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'image_id' => ['nullable', 'integer', Rule::exists('product_images', 'id')->where('product_id', $product->id)],
            'price' => ['required', 'string', $money],
            'compare_at_price' => ['nullable', 'string', $money],
            'cost' => ['nullable', 'string', $money],
            'track_inventory' => ['boolean'],
            'allow_backorder' => ['boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:9999999'],
            'length' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'width' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $sku = Str::upper(trim($data['sku']));
        if (ProductVariant::withTrashed()->whereRaw('UPPER(sku) = ?', [$sku])->whereKeyNot($variant->id)->exists()) {
            throw ValidationException::withMessages(['sku' => 'This SKU is already in use.']);
        }

        $currency = $store->currency();
        $price = Money::of($data['price'], $currency);
        $compareAt = filled($data['compare_at_price'] ?? null) ? Money::of((string) $data['compare_at_price'], $currency) : null;
        if ($compareAt !== null && ! $compareAt->greaterThan($price)) {
            throw ValidationException::withMessages(['compare_at_price' => 'The compare-at price must be higher than the price.']);
        }

        DB::transaction(function () use ($variant, $data, $sku, $currency, $price, $compareAt) {
            $variant->forceFill([
                'sku' => $sku,
                'barcode' => $data['barcode'] ?? null,
                'image_id' => $data['image_id'] ?? null,
                'cost' => filled($data['cost'] ?? null) ? Money::of((string) $data['cost'], $currency)->toDecimal() : null,
                'track_inventory' => (bool) ($data['track_inventory'] ?? $variant->track_inventory),
                'allow_backorder' => (bool) ($data['allow_backorder'] ?? false),
                'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
                'weight' => $data['weight'] ?? null,
                'length' => $data['length'] ?? null,
                'width' => $data['width'] ?? null,
                'height' => $data['height'] ?? null,
            ])->save();

            $variant->prices()->updateOrCreate(
                ['currency' => $currency],
                ['amount' => $price->toDecimal(), 'compare_at_amount' => $compareAt?->toDecimal()],
            );
        });

        /** @var User $actor */
        $actor = $request->user();
        $audit->record('product.variant_updated', $product, ['variant_id' => $variant->id, 'sku' => $sku], $actor);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Variant saved.')]);

        return redirect()->route('admin.products.variants.show', [$product, $variant]);
    }
}

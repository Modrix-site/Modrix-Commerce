<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Core\Content\RichText;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Actions\DeleteProduct;
use App\Domains\Catalog\Actions\DuplicateProduct;
use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\CategoryTree;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductType;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductAttribute;
use App\Domains\Catalog\Models\ProductAttributeValue;
use App\Domains\Catalog\Models\ProductFile;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductTag;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\MovementReason;
use App\Domains\Pricing\Money;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\User;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, StoreSettings $store): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'category' => ['nullable', 'integer'],
            'attribute' => ['nullable', 'integer', 'exists:product_attributes,id'],
            'brand' => ['nullable', 'integer'],
            'tag' => ['nullable', 'string', 'max:60'],
            'featured' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['name', '-name', 'created', '-created', 'updated', '-updated'])],
        ]);

        $sort = $filters['sort'] ?? '-updated';
        $column = match (ltrim($sort, '-')) {
            'name' => 'name',
            'created' => 'created_at',
            default => 'updated_at',
        };
        $currency = $store->currency();

        $products = Product::query()
            ->with(['variants.prices', 'variants.stocks', 'brand:id,name', 'tags', 'images' => fn ($q) => $q->limit(1)])
            ->withCount('variants')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    Like::where($q, 'name', $search);
                    $q->orWhereHas('variants', fn ($v) => Like::where($v, 'sku', $search));
                });
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['category'] ?? null, fn ($q, int $category) => $q->whereHas('categories', fn ($c) => $c->whereKey($category)))
            ->when($filters['attribute'] ?? null, fn ($q, int $attribute) => $q->whereHas('variants.options', fn ($option) => $option->where('attribute_id', $attribute)))
            ->when($filters['brand'] ?? null, fn ($q, int $brand) => $q->where('brand_id', $brand))
            // Exact (normalised) match: % and _ in a tag are literal.
            ->when($filters['tag'] ?? null, fn ($q, string $tag) => $q->whereHas('tags', fn ($t) => $t->where('tag', ProductTag::normalise($tag))))
            ->when($filters['featured'] ?? false, fn ($q) => $q->where('is_featured', true))
            ->orderBy($column, str_starts_with($sort, '-') ? 'desc' : 'asc')
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString()
            ->through(function (Product $product) use ($currency) {
                $prices = $product->variants->map(fn (ProductVariant $v) => $v->priceIn($currency))->filter();
                $tracked = $product->variants->filter(fn (ProductVariant $v) => $v->track_inventory);
                $image = $product->images->first();

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'status' => $product->status->value,
                    'type' => $product->type->value,
                    'brand' => $product->brand?->name,
                    'is_featured' => $product->is_featured,
                    'variants_count' => $product->variants_count,
                    'sku' => $product->variants->first()?->sku,
                    'price_from' => $prices->sortBy(fn ($m) => (string) $m->amount())->first(),
                    'stock' => $tracked->isEmpty() ? null : $tracked->sum(fn (ProductVariant $v) => (int) $v->onHand()),
                    'image' => $image instanceof ProductImage ? $image->url() : null,
                    'tags' => $product->tags->pluck('tag')->sort()->values()->all(),
                    'updated_at' => $product->updated_at?->toIso8601String(),
                ];
            });

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'attribute' => isset($filters['attribute']) ? (string) $filters['attribute'] : '',
                'status' => $filters['status'] ?? '',
                'category' => isset($filters['category']) ? (string) $filters['category'] : '',
                'brand' => isset($filters['brand']) ? (string) $filters['brand'] : '',
                'tag' => $filters['tag'] ?? '',
                'featured' => ($filters['featured'] ?? false) ? '1' : '',
                'sort' => $sort,
            ],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name'])->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name])->all(),
            'categories' => $this->categoryOptions(),
            'statuses' => $this->statusOptions(),
            'tagSuggestions' => $this->tagSuggestions(),
            'importErrors' => session(ProductCsvController::SESSION_ERRORS, []),
        ]);
    }

    public function create(StoreSettings $store): Response
    {
        return Inertia::render('admin/products/form', [
            'product' => null,
            ...$this->formOptions($store),
        ]);
    }

    /**
     * Copies the product as a hidden draft and opens the copy.
     */
    public function duplicate(Request $request, Product $product, DuplicateProduct $duplicate): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $copy = $duplicate->handle($product, $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product duplicated. The copy is a hidden draft.')]);

        return redirect()->route('admin.products.edit', $copy);
    }

    public function store(SaveProductRequest $request, SaveProduct $save, StoreSettings $store): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $product = $save->handle(null, $request->productData(), $store->currency(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return redirect()->route('admin.products.edit', $product);
    }

    public function edit(Product $product, StoreSettings $store, StorefrontSlots $slots): Response
    {
        $currency = $store->currency();
        $product->load(['variants.prices', 'variants.options', 'variants.stocks', 'categories:id', 'images', 'tags']);

        return Inertia::render('admin/products/form', [
            // Panels that modules add to the editor sidebar (e.g. Flash Sales).
            'extensionPanels' => $slots->render('admin.product.sidebar', ['product' => $product]),
            'product' => [
                'id' => $product->id,
                'type' => $product->type->value,
                'name' => $product->name,
                'slug' => $product->slug,
                'status' => $product->status->value,
                'published_at' => $product->published_at?->format('Y-m-d\TH:i'),
                'short_description' => $product->short_description,
                'description' => RichText::toHtml($product->description),
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
                'category_ids' => $product->categories->pluck('id')->all(),
                'brand_id' => $product->brand_id,
                'is_featured' => $product->is_featured,
                'show_buy_button' => $product->show_buy_button,
                'tags' => $product->tags->sortBy('tag')->pluck('tag')->values()->all(),
                'related' => $product->relatedProducts()->with(['images', 'variants'])->get()->map(fn (Product $p) => self::pickerItem($p))->all(),
                'upsells' => $product->upsellProducts()->with(['images', 'variants'])->get()->map(fn (Product $p) => self::pickerItem($p))->all(),
                'view_url' => $product->isVisible() ? route('storefront.product', $product->slug) : null,
                'option_attribute_ids' => $product->variants
                    ->flatMap(fn (ProductVariant $v) => $v->options->pluck('attribute_id'))
                    ->unique()->values()->all(),
                'variants' => $product->variants->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'name' => $v->name,
                    'image_id' => $v->image_id,
                    'price' => $v->priceIn($currency)?->toDisplayDecimal() ?? '',
                    'compare_at_price' => $v->compareAtPriceIn($currency)?->toDisplayDecimal() ?? '',
                    'weight' => $v->weight,
                    'cost' => $v->cost === null ? '' : Money::of($v->cost, $currency)->toDisplayDecimal(),
                    'length' => $v->length,
                    'width' => $v->width,
                    'height' => $v->height,
                    'barcode' => $v->barcode,
                    'track_inventory' => $v->track_inventory,
                    'allow_backorder' => $v->allow_backorder,
                    'is_active' => $v->is_active,
                    'low_stock_threshold' => $v->low_stock_threshold,
                    'option_value_ids' => $v->options->pluck('id')->all(),
                    'on_hand' => $v->onHand(),
                ])->values()->all(),
                'is_digital' => $product->is_digital,
                'files' => $product->files->map(fn (ProductFile $file) => [
                    'id' => $file->id,
                    'name' => $file->name,
                    'size' => $file->size,
                    'mime' => $file->mime,
                ])->values()->all(),
                'images' => $product->images->map(fn (ProductImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url(),
                    'alt' => $image->alt,
                    'width' => $image->width,
                    'height' => $image->height,
                ])->values()->all(),
            ],
            ...$this->formOptions($store),
        ]);
    }

    public function update(SaveProductRequest $request, Product $product, SaveProduct $save, StoreSettings $store): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $save->handle($product, $request->productData(), $store->currency(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product saved.')]);

        return redirect()->route('admin.products.edit', $product);
    }

    /**
     * Product search for the related products and upsells pickers.
     */
    public function relatedSearch(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('q', ''));

        $products = Product::query()->with(['images', 'variants'])
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                Like::where($q, 'name', $search);
                $q->orWhereHas('variants', fn ($v) => Like::where($v, 'sku', $search));
            }))
            ->orderBy('name')->limit(20)->get();

        return response()->json($products->map(fn (Product $p) => self::pickerItem($p))->values());
    }

    /**
     * @return array{id: int, name: string, sku: string|null, image: string|null}
     */
    private static function pickerItem(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'sku' => $product->variants->first()?->sku,
            'image' => $product->images->first()?->url(),
        ];
    }

    public function destroy(Product $product, DeleteProduct $delete): RedirectResponse
    {
        $delete->handle($product);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product deleted.')]);

        return redirect()->route('admin.products.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(StoreSettings $store): array
    {
        return [
            'currency' => $store->currency(),
            'inventoryReasons' => array_map(fn (MovementReason $reason) => ['value' => $reason->value, 'label' => $reason->label()], MovementReason::manual()),
            'weightUnit' => $store->toArray()['weight_unit'],
            'dimensionUnit' => $store->toArray()['dimension_unit'],
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name'])->map(fn (Brand $b) => ['id' => $b->id, 'name' => $b->name])->all(),
            'tagSuggestions' => $this->tagSuggestions(),
            'categories' => $this->categoryOptions(),
            'statuses' => $this->statusOptions(),
            'types' => collect(ProductType::cases())->map(fn (ProductType $t) => ['value' => $t->value, 'label' => $t->label()])->all(),
            'attributes' => ProductAttribute::query()->with('values')->orderBy('position')->orderBy('name')->get()
                ->map(fn (ProductAttribute $a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'values' => $a->values->map(fn (ProductAttributeValue $v) => ['id' => $v->id, 'value' => $v->value])->all(),
                ])->all(),
        ];
    }

    /**
     * @return list<array{id: int, name: string, depth: int}>
     */
    private function categoryOptions(): array
    {
        return array_map(fn ($c) => ['id' => $c['id'], 'name' => $c['name'], 'depth' => $c['depth']], CategoryTree::flatten());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(fn (ProductStatus $s) => ['value' => $s->value, 'label' => $s->label()], ProductStatus::cases());
    }

    /**
     * Tags already used in the catalog, most used first.
     *
     * @return list<string>
     */
    private function tagSuggestions(): array
    {
        return ProductTag::query()->selectRaw('tag, count(*) as uses')->groupBy('tag')->orderByDesc('uses')->orderBy('tag')->limit(200)->pluck('tag')->all();
    }
}

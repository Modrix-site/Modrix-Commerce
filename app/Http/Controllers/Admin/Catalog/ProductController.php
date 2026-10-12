<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Actions\DeleteProduct;
use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\CategoryTree;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Enums\ProductType;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductAttribute;
use App\Domains\Catalog\Models\ProductAttributeValue;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveProductRequest;
use App\Models\User;
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
            ->with(['variants.prices', 'variants.stocks', 'images' => fn ($q) => $q->limit(1)])
            ->withCount('variants')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('name', 'like', $like)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $like)));
            })
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['category'] ?? null, fn ($q, int $category) => $q->whereHas('categories', fn ($c) => $c->whereKey($category)))
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
                    'variants_count' => $product->variants_count,
                    'sku' => $product->variants->first()?->sku,
                    'price_from' => $prices->sortBy(fn ($m) => (string) $m->amount())->first(),
                    'stock' => $tracked->isEmpty() ? null : $tracked->sum(fn (ProductVariant $v) => (int) $v->onHand()),
                    'image' => $image instanceof ProductImage ? $image->url() : null,
                    'updated_at' => $product->updated_at?->toIso8601String(),
                ];
            });

        return Inertia::render('admin/products/index', [
            'products' => $products,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'status' => $filters['status'] ?? '',
                'category' => isset($filters['category']) ? (string) $filters['category'] : '',
                'sort' => $sort,
            ],
            'categories' => $this->categoryOptions(),
            'statuses' => $this->statusOptions(),
        ]);
    }

    public function create(StoreSettings $store): Response
    {
        return Inertia::render('admin/products/form', [
            'product' => null,
            ...$this->formOptions($store),
        ]);
    }

    public function store(SaveProductRequest $request, SaveProduct $save, StoreSettings $store): RedirectResponse
    {
        /** @var User $actor */
        $actor = $request->user();
        $product = $save->handle(null, $request->productData(), $store->currency(), $actor);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Product created.')]);

        return redirect()->route('admin.products.edit', $product);
    }

    public function edit(Product $product, StoreSettings $store): Response
    {
        $currency = $store->currency();
        $product->load(['variants.prices', 'variants.options', 'variants.stocks', 'categories:id', 'images']);

        return Inertia::render('admin/products/form', [
            'product' => [
                'id' => $product->id,
                'type' => $product->type->value,
                'name' => $product->name,
                'slug' => $product->slug,
                'status' => $product->status->value,
                'published_at' => $product->published_at?->format('Y-m-d\TH:i'),
                'short_description' => $product->short_description,
                'description' => $product->description,
                'meta_title' => $product->meta_title,
                'meta_description' => $product->meta_description,
                'category_ids' => $product->categories->pluck('id')->all(),
                'option_attribute_ids' => $product->variants
                    ->flatMap(fn (ProductVariant $v) => $v->options->pluck('attribute_id'))
                    ->unique()->values()->all(),
                'variants' => $product->variants->map(fn (ProductVariant $v) => [
                    'id' => $v->id,
                    'sku' => $v->sku,
                    'name' => $v->name,
                    'price' => $v->priceIn($currency)?->toDisplayDecimal() ?? '',
                    'compare_at_price' => $v->compareAtPriceIn($currency)?->toDisplayDecimal() ?? '',
                    'weight' => $v->weight,
                    'barcode' => $v->barcode,
                    'track_inventory' => $v->track_inventory,
                    'allow_backorder' => $v->allow_backorder,
                    'is_active' => $v->is_active,
                    'low_stock_threshold' => $v->low_stock_threshold,
                    'option_value_ids' => $v->options->pluck('id')->all(),
                    'on_hand' => $v->onHand(),
                ])->values()->all(),
                'images' => $product->images->map(fn (ProductImage $image) => [
                    'id' => $image->id,
                    'url' => $image->url(),
                    'alt' => $image->alt,
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
}

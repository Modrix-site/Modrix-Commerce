<?php

namespace EcoModular\ProductAddons\Http;

use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use EcoModular\ProductAddons\Models\ProductAddon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AddonAdminController
{
    public function index(StoreSettings $store): Response
    {
        $addons = ProductAddon::query()
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->get();

        $productIds = [];
        $categoryIds = [];

        foreach ($addons as $addon) {
            $ids = array_map('intval', $addon->target_ids ?? []);
            if ($addon->target_type === ProductAddon::TARGET_PRODUCTS) {
                $productIds = [...$productIds, ...$ids];
            } elseif ($addon->target_type === ProductAddon::TARGET_CATEGORIES) {
                $categoryIds = [...$categoryIds, ...$ids];
            }
        }

        $products = Product::query()
            ->whereIn('id', array_unique($productIds))
            ->get()
            ->keyBy('id');

        $categories = Category::query()
            ->whereIn('id', array_unique($categoryIds))
            ->get()
            ->keyBy('id');

        $statsByAddon = Schema::hasTable('product_addon_orders')
            ? DB::table('product_addon_orders')
                ->selectRaw('addon_id, SUM(quantity) as units_sold, SUM(total_price) as revenue')
                ->whereNotNull('addon_id')
                ->groupBy('addon_id')
                ->get()
                ->keyBy('addon_id')
            : collect();

        return Inertia::render('module/modrixcommerce/product-addons/admin/index', [
            'currency' => $store->currency(),
            'addons' => $addons->map(function (ProductAddon $addon) use ($products, $categories, $statsByAddon) {
                $ids = array_map('intval', $addon->target_ids ?? []);
                $targets = [];

                if ($addon->target_type === ProductAddon::TARGET_PRODUCTS) {
                    foreach ($ids as $id) {
                        if ($p = $products->get($id)) {
                            $targets[] = ['id' => $p->id, 'name' => $p->localized('name')];
                        }
                    }
                } elseif ($addon->target_type === ProductAddon::TARGET_CATEGORIES) {
                    foreach ($ids as $id) {
                        if ($c = $categories->get($id)) {
                            $targets[] = ['id' => $c->id, 'name' => $c->localized('name')];
                        }
                    }
                }

                $stat = $statsByAddon->get($addon->id);

                return [
                    'id' => $addon->id,
                    'name' => $addon->name,
                    'type' => $addon->type,
                    'badge' => $addon->badge,
                    'description' => $addon->description,
                    'price' => (string) $addon->price,
                    'target_type' => $addon->target_type,
                    'target_ids' => $ids,
                    'targets' => $targets,
                    'priority' => $addon->priority,
                    'is_active' => $addon->is_active,
                    'units_sold' => (int) ($stat->units_sold ?? 0),
                    'revenue' => (string) ($stat->revenue ?? '0.00'),
                ];
            })->values()->all(),
        ]);
    }

    public function create(StoreSettings $store): Response
    {
        return Inertia::render('module/modrixcommerce/product-addons/admin/form', [
            'addon' => null,
            'currency' => $store->currency(),
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function edit(ProductAddon $addon, StoreSettings $store): Response
    {
        $targetIds = array_map('intval', $addon->target_ids ?? []);
        $selectedProducts = $addon->target_type === ProductAddon::TARGET_PRODUCTS && $targetIds !== []
            ? Product::query()->with('images')->whereIn('id', $targetIds)->get()->map(fn (Product $p) => $this->productOption($p))->values()->all()
            : [];

        return Inertia::render('module/modrixcommerce/product-addons/admin/form', [
            'currency' => $store->currency(),
            'categories' => $this->categoryOptions(),
            'addon' => [
                'id' => $addon->id,
                'name' => $addon->name,
                'type' => $addon->type,
                'badge' => $addon->badge ?? '',
                'description' => $addon->description ?? '',
                'price' => number_format((float) $addon->price, 2, '.', ''),
                'target_type' => $addon->target_type,
                'target_ids' => $targetIds,
                'selected_products' => $selectedProducts,
                'priority' => $addon->priority,
                'is_active' => $addon->is_active,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        ProductAddon::query()->create($this->validated($request));

        return to_route('admin.modrixcommerce.product-addons.index')
            ->with('success', 'Product addon created.');
    }

    public function update(Request $request, ProductAddon $addon): RedirectResponse
    {
        $addon->update($this->validated($request));

        return to_route('admin.modrixcommerce.product-addons.index')
            ->with('success', 'Product addon updated.');
    }

    public function destroy(ProductAddon $addon): RedirectResponse
    {
        $addon->delete();

        return to_route('admin.modrixcommerce.product-addons.index')
            ->with('success', 'Product addon deleted.');
    }

    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $products = Product::query()
            ->with('images')
            ->when($q !== '', fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('slug', 'like', "%{$q}%"))
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (Product $p) => $this->productOption($p))
            ->values();

        return response()->json(['products' => $products]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['required', Rule::in([
                ProductAddon::TYPE_WARRANTY,
                ProductAddon::TYPE_FEATURE,
                ProductAddon::TYPE_SERVICE,
            ])],
            'badge' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:999999'],
            'target_type' => ['required', Rule::in([
                ProductAddon::TARGET_ALL,
                ProductAddon::TARGET_PRODUCTS,
                ProductAddon::TARGET_CATEGORIES,
            ])],
            'target_ids' => ['nullable', 'array'],
            'target_ids.*' => ['integer'],
            'priority' => ['nullable', 'integer', 'min:-999', 'max:9999'],
            'is_active' => ['boolean'],
        ]);

        $targetIds = $data['target_type'] === ProductAddon::TARGET_ALL
            ? []
            : array_values(array_unique(array_map('intval', $data['target_ids'] ?? [])));

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'badge' => $data['badge'] ?: null,
            'description' => $data['description'] ?: null,
            'price' => $data['price'],
            'target_type' => $data['target_type'],
            'target_ids' => $targetIds,
            'priority' => (int) ($data['priority'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function categoryOptions(): array
    {
        return Category::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->localized('name'),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, slug: string, image: string|null}
     */
    private function productOption(Product $product): array
    {
        $image = $product->images->first();

        return [
            'id' => $product->id,
            'name' => $product->localized('name'),
            'slug' => $product->slug,
            'image' => $image?->url(),
        ];
    }
}

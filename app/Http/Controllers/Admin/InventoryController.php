<?php

namespace App\Http\Controllers\Admin;

use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\CommittedStock;
use App\Domains\Inventory\Exceptions\InsufficientStock;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\MovementReason;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, InventoryService $inventory, SettingsRepository $settings, CommittedStock $committedStock): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'stock' => ['nullable', Rule::in(['low', 'out'])],
        ]);

        $defaultThreshold = (int) $settings->get('inventory.low_stock_threshold', InventoryService::DEFAULT_LOW_STOCK_THRESHOLD);
        $locationId = $inventory->defaultLocation()->id;
        $search = $filters['search'] ?? null;
        $stock = $filters['stock'] ?? null;

        // Tracked variants with their stock; the filters decide which variants "match".
        $variantQuery = fn () => ProductVariant::query()
            ->leftJoin('inventory_stocks', fn ($join) => $join
                ->on('inventory_stocks.variant_id', '=', 'product_variants.id')
                ->where('inventory_stocks.location_id', $locationId))
            ->where('product_variants.track_inventory', true);
        $matching = fn ($query) => $query
            ->when($search, fn ($q, string $term) => $q->where(function ($q) use ($term) {
                Like::where($q, 'product_variants.sku', $term);
                $q->orWhereIn('product_variants.product_id', fn ($products) => Like::where($products->select('id')->from('products'), 'name', $term));
            }))
            ->when($stock === 'out', fn ($q) => $q->whereRaw('COALESCE(inventory_stocks.on_hand, 0) <= 0'))
            ->when($stock === 'low', fn ($q) => $q->whereRaw(
                'COALESCE(inventory_stocks.on_hand, 0) <= COALESCE(product_variants.low_stock_threshold, ?)',
                [$defaultThreshold],
            ));

        // One row per product: most urgent first (its lowest tracked variant).
        $products = Product::query()
            ->select('products.id', 'products.name')
            ->whereIn('products.id', $matching($variantQuery())->select('product_variants.product_id'))
            ->selectSub(
                $variantQuery()->whereColumn('product_variants.product_id', 'products.id')->selectRaw('MIN(COALESCE(inventory_stocks.on_hand, 0))'),
                'lowest_on_hand',
            )
            ->with(['images' => fn ($q) => $q->orderBy('position')])
            ->withCount(['variants as untracked_count' => fn ($q) => $q->where('track_inventory', false)])
            ->orderBy('lowest_on_hand')
            ->orderBy('products.name')
            ->paginate(30)
            ->withQueryString();

        $pageIds = $products->getCollection()->pluck('id')->all();
        $variants = $variantQuery()
            ->select('product_variants.*')
            ->selectRaw('COALESCE(inventory_stocks.on_hand, 0) as stock_on_hand')
            ->whereIn('product_variants.product_id', $pageIds)
            ->orderBy('product_variants.position')
            ->get()
            ->groupBy('product_id');
        $matched = $search || $stock
            ? array_flip($matching($variantQuery())->whereIn('product_variants.product_id', $pageIds)->pluck('product_variants.id')->all())
            : [];
        $committed = $committedStock->forVariants($variants->flatten()->pluck('id')->all());

        $products->through(function (Product $product) use ($variants, $defaultThreshold, $matched, $committed) {
            $rows = ($variants->get($product->id) ?? collect())->map(function (ProductVariant $variant) use ($defaultThreshold, $matched, $committed) {
                $onHand = (int) $variant->getAttribute('stock_on_hand');
                $threshold = $variant->low_stock_threshold ?? $defaultThreshold;

                return [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'name' => $variant->name,
                    'available' => $onHand,
                    'committed' => $committed[$variant->id] ?? 0,
                    'threshold' => $threshold,
                    'state' => $onHand <= 0 ? 'out' : ($onHand <= $threshold ? 'low' : 'ok'),
                    'allow_backorder' => $variant->allow_backorder,
                    'match' => isset($matched[$variant->id]),
                    'url' => route('admin.products.variants.show', [$variant->product_id, $variant->id]),
                ];
            })->values();

            return [
                'id' => $product->id,
                'name' => $product->name,
                'image_url' => $product->images->first()?->url(),
                'available' => $rows->sum('available'),
                'committed' => $rows->sum('committed'),
                'low' => $rows->where('state', 'low')->count(),
                'out' => $rows->where('state', 'out')->count(),
                'untracked' => (int) $product->getAttribute('untracked_count'),
                'variants' => $rows->all(),
            ];
        });

        return Inertia::render('admin/inventory/index', [
            'products' => $products,
            'filters' => ['search' => $filters['search'] ?? '', 'stock' => $filters['stock'] ?? ''],
            'reasons' => array_map(fn (MovementReason $r) => ['value' => $r->value, 'label' => $r->label()], MovementReason::manual()),
            'canManage' => $request->user()?->can('inventory.manage') ?? false,
        ]);
    }

    public function adjust(Request $request, ProductVariant $variant, InventoryService $inventory): RedirectResponse
    {
        $data = $request->validate([
            'mode' => ['required', Rule::in(['add', 'remove', 'set'])],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'reason' => ['required', Rule::in(array_map(fn (MovementReason $r) => $r->value, MovementReason::manual()))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $variant->track_inventory) {
            throw ValidationException::withMessages(['quantity' => 'Inventory is not tracked for this variant.']);
        }

        /** @var User $actor */
        $actor = $request->user();
        $reason = MovementReason::from($data['reason']);
        $quantity = (int) $data['quantity'];

        try {
            match ($data['mode']) {
                'set' => $inventory->setOnHand($variant, $quantity, $reason, $actor, $data['note'] ?? null),
                'add' => $quantity > 0 ? $inventory->adjust($variant, $quantity, $reason, $actor, $data['note'] ?? null) : null,
                default => $quantity > 0 ? $inventory->adjust($variant, -$quantity, $reason, $actor, $data['note'] ?? null) : null,
            };
        } catch (InsufficientStock $e) {
            throw ValidationException::withMessages(['quantity' => "Only {$e->shortages[0]['available']} in stock; this variant does not allow backorders."]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock updated for :sku.', ['sku' => $variant->sku])]);

        return back();
    }

    public function movements(Request $request): Response
    {
        $filters = $request->validate([
            'sku' => ['nullable', 'string', 'max:64'],
            'reason' => ['nullable', Rule::enum(MovementReason::class)],
        ]);

        $movements = InventoryMovement::query()
            ->with(['variant:id,sku,product_id,name', 'variant.product:id,name', 'actor:id,email'])
            ->when($filters['sku'] ?? null, fn ($q, string $sku) => $q->whereHas('variant', fn ($v) => $v->withTrashed()->where('sku', $sku)))
            ->when($filters['reason'] ?? null, fn ($q, string $reason) => $q->where('reason', $reason))
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (InventoryMovement $m) => [
                'id' => $m->id,
                'sku' => $m->variant->sku,
                'name' => $m->variant->displayName(),
                'change' => $m->quantity_change,
                'after' => $m->quantity_after,
                'reason' => $m->reason->label(),
                'actor' => $m->actor?->email,
                'note' => $m->note,
                'reference' => $m->reference_type ? class_basename($m->reference_type).' #'.$m->reference_id : null,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return Inertia::render('admin/inventory/movements', [
            'movements' => $movements,
            'filters' => ['sku' => $filters['sku'] ?? '', 'reason' => $filters['reason'] ?? ''],
            'reasons' => array_map(fn (MovementReason $r) => ['value' => $r->value, 'label' => $r->label()], MovementReason::cases()),
        ]);
    }
}

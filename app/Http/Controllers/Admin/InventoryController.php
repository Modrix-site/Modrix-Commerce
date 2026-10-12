<?php

namespace App\Http\Controllers\Admin;

use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Inventory\Exceptions\InsufficientStock;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\Models\InventoryMovement;
use App\Domains\Inventory\MovementReason;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request, InventoryService $inventory, SettingsRepository $settings): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'stock' => ['nullable', Rule::in(['low', 'out'])],
        ]);

        $defaultThreshold = (int) $settings->get('inventory.low_stock_threshold', InventoryService::DEFAULT_LOW_STOCK_THRESHOLD);
        $locationId = $inventory->defaultLocation()->id;

        $variants = ProductVariant::query()
            ->select('product_variants.*')
            ->selectRaw('COALESCE(inventory_stocks.on_hand, 0) as stock_on_hand')
            ->leftJoin('inventory_stocks', fn ($join) => $join
                ->on('inventory_stocks.variant_id', '=', 'product_variants.id')
                ->where('inventory_stocks.location_id', $locationId))
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereNull('products.deleted_at')
            ->where('product_variants.track_inventory', true)
            ->with('product:id,name')
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(fn ($q) => $q->where('product_variants.sku', 'like', $like)->orWhere('products.name', 'like', $like));
            })
            ->when(($filters['stock'] ?? null) === 'out', fn ($q) => $q->whereRaw('COALESCE(inventory_stocks.on_hand, 0) <= 0'))
            ->when(($filters['stock'] ?? null) === 'low', fn ($q) => $q->whereRaw(
                'COALESCE(inventory_stocks.on_hand, 0) <= COALESCE(product_variants.low_stock_threshold, ?)',
                [$defaultThreshold],
            ))
            ->orderBy('stock_on_hand')
            ->orderBy('product_variants.sku')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ProductVariant $variant) => [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'product_id' => $variant->product_id,
                'name' => $variant->displayName(),
                'on_hand' => (int) $variant->getAttribute('stock_on_hand'),
                'threshold' => $variant->low_stock_threshold ?? $defaultThreshold,
                'allow_backorder' => $variant->allow_backorder,
            ]);

        return Inertia::render('admin/inventory/index', [
            'variants' => $variants,
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

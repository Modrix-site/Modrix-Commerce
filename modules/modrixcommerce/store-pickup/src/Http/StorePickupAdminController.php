<?php

namespace EcoModular\StorePickup\Http;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\Countries;
use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Models\OrderEvent;
use App\Domains\Orders\OrderDisplayStatus;
use App\Http\Controllers\Controller;
use EcoModular\StorePickup\Models\OrderPickup;
use EcoModular\StorePickup\Models\PickupBranch;
use EcoModular\StorePickup\StorePickupSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class StorePickupAdminController extends Controller
{
    private const ROUTE = 'admin.modrixcommerce.store-pickup.';

    public function __construct(
        private readonly StorePickupSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->query('status', 'all');
        $branchId = $request->query('branch_id', 'all');
        $search = trim((string) $request->query('search', ''));
        $tab = $request->query('tab', 'orders');

        $query = OrderPickup::query()->with(['order', 'branch'])->latest('id');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($branchId !== 'all') {
            $query->where('pickup_branch_id', $branchId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('branch_name', 'like', "%{$search}%")
                    ->orWhere('collector_name', 'like', "%{$search}%")
                    ->orWhere('collector_phone', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($oq) use ($search) {
                        $oq->where('number', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        $orders = $query->paginate(25)->through(fn (OrderPickup $p) => [
            'id' => $p->id,
            'order_id' => $p->order_id,
            'order_number' => $p->order?->number ?? "Order #{$p->order_id}",
            'order_url' => $p->order ? route('admin.orders.show', $p->order) : null,
            'customer_email' => $p->order?->email,
            'grand_total' => $p->order?->grand_total ? (string) $p->order->grand_total : null,
            'currency' => $p->order?->currency ?? 'USD',
            'branch_id' => $p->pickup_branch_id,
            'branch_name' => $p->branch_name,
            'branch_address' => $p->branch_address,
            'branch_phone' => $p->branch_phone,
            'pickup_hours' => $p->pickup_hours,
            'collector_name' => $p->collector_name,
            'collector_phone' => $p->collector_phone,
            'pickup_date' => $p->pickup_date?->toDateString(),
            'pickup_time' => $p->pickup_time,
            'status' => $p->status,
            'badge' => $p->statusBadge(),
            'ready_at' => $p->ready_at?->toIso8601String(),
            'picked_up_at' => $p->picked_up_at?->toIso8601String(),
            'customer_notes' => $p->customer_notes,
            'created_at' => $p->created_at->toIso8601String(),
            'update_status_url' => route(self::ROUTE.'orders.status', $p),
        ]);

        $branches = PickupBranch::query()
            ->withCount('orderPickups')
            ->orderBy('order')
            ->orderBy('name')
            ->get()
            ->map(fn (PickupBranch $b) => [
                ...$b->toPresentable(),
                'orders_count' => $b->order_pickups_count,
                'update_url' => route(self::ROUTE.'branches.update', $b),
                'delete_url' => route(self::ROUTE.'branches.destroy', $b),
            ]);

        return Inertia::render('module/modrixcommerce/store-pickup/admin/index', [
            'counts' => [
                'total_orders' => OrderPickup::query()->count(),
                'pending' => OrderPickup::query()->where('status', OrderPickup::STATUS_PENDING)->count(),
                'ready' => OrderPickup::query()->where('status', OrderPickup::STATUS_READY)->count(),
                'picked_up' => OrderPickup::query()->where('status', OrderPickup::STATUS_PICKED_UP)->count(),
                'branches' => PickupBranch::query()->count(),
                'active_branches' => PickupBranch::query()->where('is_active', true)->count(),
            ],
            'filters' => [
                'status' => $status,
                'branch_id' => $branchId,
                'search' => $search,
                'tab' => $tab,
            ],
            'orders' => $orders,
            'branches' => $branches,
            'countries' => Countries::options(),
            'settings' => $this->settings->toArray(),
            'urls' => [
                'index' => route(self::ROUTE.'index'),
                'store_branch' => route(self::ROUTE.'branches.store'),
                'save_settings' => route(self::ROUTE.'settings.save'),
            ],
        ]);
    }

    public function storeBranch(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:60', 'unique:pickup_branches,code'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'pickup_hours' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'prep_time' => ['nullable', 'string', 'max:100'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'order' => ['nullable', 'integer'],
        ]);

        $code = Str::slug(! empty($validated['code']) ? $validated['code'] : $validated['name']);
        if ($code === '' || PickupBranch::query()->where('code', $code)->exists()) {
            $code .= '-'.Str::random(4);
        }

        $branch = PickupBranch::create([
            'name' => $validated['name'],
            'code' => $code,
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'region' => $validated['region'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
            'country_code' => strtoupper($validated['country_code']),
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'pickup_hours' => $validated['pickup_hours'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'prep_time' => $validated['prep_time'] ?? 'Ready in 2-4 hours',
            'fee' => (float) ($validated['fee'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'order' => (int) ($validated['order'] ?? 0),
        ]);

        $audit->record('store_pickup.branch_created', null, ['id' => $branch->id, 'name' => $branch->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Branch \"{$branch->name}\" added successfully."]);

        return back();
    }

    public function updateBranch(PickupBranch $branch, Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'code' => ['nullable', 'string', 'max:60', 'unique:pickup_branches,code,'.$branch->id],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:32'],
            'country_code' => ['required', 'string', 'size:2'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:191'],
            'pickup_hours' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'prep_time' => ['nullable', 'string', 'max:100'],
            'fee' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'order' => ['nullable', 'integer'],
        ]);

        if (! empty($validated['code'])) {
            $validated['code'] = Str::slug($validated['code']);
        }

        $branch->update([
            ...$validated,
            'country_code' => strtoupper($validated['country_code']),
            'fee' => (float) ($validated['fee'] ?? 0),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'order' => (int) ($validated['order'] ?? 0),
        ]);

        $audit->record('store_pickup.branch_updated', null, ['id' => $branch->id, 'name' => $branch->name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Branch \"{$branch->name}\" updated."]);

        return back();
    }

    public function destroyBranch(PickupBranch $branch, AuditLogger $audit): RedirectResponse
    {
        $name = $branch->name;
        $branch->delete();

        $audit->record('store_pickup.branch_deleted', null, ['name' => $name]);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Branch \"{$name}\" removed."]);

        return back();
    }

    public function updateOrderStatus(OrderPickup $orderPickup, Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending_prep,ready_for_pickup,picked_up,cancelled'],
        ]);

        $newStatus = $validated['status'];
        $updateData = ['status' => $newStatus];

        if ($newStatus === OrderPickup::STATUS_READY && ! $orderPickup->ready_at) {
            $updateData['ready_at'] = now();
        }

        if ($newStatus === OrderPickup::STATUS_PICKED_UP && ! $orderPickup->picked_up_at) {
            $updateData['picked_up_at'] = now();
        }

        $orderPickup->update($updateData);

        // Synchronize headline order status with Store Pickup lifecycle
        $order = $orderPickup->order;
        if ($order) {
            if ($newStatus === OrderPickup::STATUS_PICKED_UP) {
                $order->fulfillment_status = FulfillmentStatus::Delivered;
                $order->status = OrderStatus::Completed;
            } elseif ($newStatus === OrderPickup::STATUS_READY) {
                $order->fulfillment_status = FulfillmentStatus::ReadyForDispatch;
            } elseif ($newStatus === OrderPickup::STATUS_PENDING) {
                $order->fulfillment_status = FulfillmentStatus::Unfulfilled;
            }
            $order->display_status = OrderDisplayStatus::for($order)->value;
            $order->save();
        }

        // Record event in order timeline
        $statusLabels = [
            OrderPickup::STATUS_PENDING => 'Preparation in progress',
            OrderPickup::STATUS_READY => 'READY FOR PICKUP at '.$orderPickup->branch_name,
            OrderPickup::STATUS_PICKED_UP => 'Picked up / Order collected by customer',
            OrderPickup::STATUS_CANCELLED => 'Store pickup cancelled',
        ];

        OrderEvent::query()->forceCreate([
            'order_id' => $orderPickup->order_id,
            'type' => 'status',
            'message' => "Store pickup status updated: {$statusLabels[$newStatus]}.",
            'is_customer_visible' => true,
        ]);

        $audit->record('store_pickup.order_status_updated', null, [
            'order_id' => $orderPickup->order_id,
            'status' => $newStatus,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Pickup status updated to {$statusLabels[$newStatus]}."]);

        return back();
    }

    public function saveSettings(Request $request, SettingsRepository $settingsRepo, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'enabled' => ['required', 'boolean'],
            'shipping_label' => ['required', 'string', 'max:120'],
            'allow_alternative_collector' => ['required', 'boolean'],
            'allow_schedule' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'cod_behavior' => ['required', 'string', 'in:pay_in_store,hide,keep'],
            'pay_in_store_label' => ['nullable', 'string', 'max:120'],
            'pay_in_store_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $prefix = StorePickupSettings::PREFIX;
        $settingsRepo->set("{$prefix}.enabled", (bool) $validated['enabled']);
        $settingsRepo->set("{$prefix}.shipping_label", $validated['shipping_label']);
        $settingsRepo->set("{$prefix}.allow_alternative_collector", (bool) $validated['allow_alternative_collector']);
        $settingsRepo->set("{$prefix}.allow_schedule", (bool) $validated['allow_schedule']);
        $settingsRepo->set("{$prefix}.instructions", $validated['instructions'] ?? '');
        $settingsRepo->set("{$prefix}.cod_behavior", $validated['cod_behavior']);
        $settingsRepo->set("{$prefix}.pay_in_store_label", $validated['pay_in_store_label'] ?: 'Pay in store');
        $settingsRepo->set("{$prefix}.pay_in_store_instructions", $validated['pay_in_store_instructions'] ?: 'Pay in store (cash or card) when you collect your order.');

        $audit->record('store_pickup.settings_updated', null, $validated);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Store pickup settings saved.']);

        return back();
    }
}

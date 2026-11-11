<?php

namespace EcoModular\StorePickup\Http;

use App\Http\Controllers\Controller;
use EcoModular\StorePickup\Models\PickupBranch;
use EcoModular\StorePickup\StorePickupSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorePickupStorefrontController extends Controller
{
    public function __construct(
        private readonly StorePickupSettings $settings,
    ) {}

    public function branches(): JsonResponse
    {
        $branches = PickupBranch::active()->get()->map(fn (PickupBranch $b) => $b->toPresentable());

        return response()->json([
            'enabled' => $this->settings->enabled(),
            'shipping_label' => $this->settings->shippingLabel(),
            'branches' => $branches,
        ]);
    }

    public function select(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:pickup_branches,id'],
            'collector_name' => ['nullable', 'string', 'max:120'],
            'collector_phone' => ['nullable', 'string', 'max:50'],
            'pickup_date' => ['nullable', 'date'],
            'pickup_time' => ['nullable', 'string', 'max:50'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var PickupBranch $branch */
        $branch = PickupBranch::query()->where('is_active', true)->findOrFail($validated['branch_id']);

        $selection = [
            'branch_id' => $branch->id,
            'branch_name' => $branch->name,
            'branch_address' => $branch->fullAddress(),
            'branch_phone' => $branch->phone,
            'pickup_hours' => $branch->pickup_hours ?: 'Standard store hours',
            'prep_time' => $branch->prep_time ?: 'Ready in 2-4 hours',
            'collector_name' => $validated['collector_name'] ?? null,
            'collector_phone' => $validated['collector_phone'] ?? null,
            'pickup_date' => $validated['pickup_date'] ?? null,
            'pickup_time' => $validated['pickup_time'] ?? null,
            'customer_notes' => $validated['customer_notes'] ?? null,
            'fee' => (float) $branch->fee,
        ];

        session(['store_pickup_selection' => $selection]);

        return response()->json([
            'success' => true,
            'message' => "Selected {$branch->name} for pickup.",
            'selection' => $selection,
            'branch' => $branch->toPresentable(),
        ]);
    }

    public function clear(): JsonResponse
    {
        session()->forget('store_pickup_selection');

        return response()->json([
            'success' => true,
            'message' => 'Store pickup selection cleared.',
        ]);
    }
}

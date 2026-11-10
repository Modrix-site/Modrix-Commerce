<?php

namespace EcoModular\DeliverySlots\Http;

use EcoModular\DeliverySlots\DeliverySlotResolver;
use EcoModular\DeliverySlots\DeliverySlotsSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class DeliverySlotsStorefrontController extends Controller
{
    public function select(Request $request, DeliverySlotResolver $resolver, DeliverySlotsSettings $settings): JsonResponse
    {
        if (! $settings->enabled()) {
            return response()->json(['success' => false, 'message' => 'Delivery slots are disabled.'], 403);
        }

        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'delivery_slot_id' => ['required', 'integer'],
            'delivery_instructions' => ['nullable', 'string', 'max:500'],
        ]);

        $check = $resolver->validateSelection($validated['date'], (int) $validated['delivery_slot_id']);
        if (! $check['valid']) {
            return response()->json([
                'success' => false,
                'message' => $check['error'] ?? 'Selected delivery slot is not available.',
            ], 422);
        }

        $slot = $check['slot'];
        $selection = [
            'date' => $validated['date'],
            'delivery_slot_id' => $slot->id,
            'slot_name' => $slot->name,
            'instructions' => $validated['delivery_instructions'] ?? null,
        ];

        session()->put('delivery_slots_selection', $selection);

        return response()->json([
            'success' => true,
            'selection' => $selection,
        ]);
    }

    public function slotsForDate(Request $request, DeliverySlotResolver $resolver): JsonResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
        ]);

        $slots = $resolver->getSlotsForDate($validated['date']);

        return response()->json([
            'slots' => $slots,
        ]);
    }

    public function clear(): JsonResponse
    {
        session()->forget('delivery_slots_selection');

        return response()->json(['success' => true]);
    }
}

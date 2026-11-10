<?php

namespace EcoModular\DeliverySlots\Listeners;

use EcoModular\DeliverySlots\DeliverySlotsSettings;
use EcoModular\DeliverySlots\Models\DeliverySlot;
use EcoModular\DeliverySlots\Models\OrderDeliverySchedule;
use Illuminate\Support\Facades\Log;

class RecordOrderDeliverySchedule
{
    public function __construct(
        private readonly DeliverySlotsSettings $settings,
    ) {}

    public function handle(mixed $event): void
    {
        if (! $this->settings->enabled()) {
            return;
        }

        $orderId = is_numeric($event) ? (int) $event : ($event->orderId ?? null);
        if (! $orderId) {
            return;
        }

        if (OrderDeliverySchedule::query()->where('order_id', $orderId)->exists()) {
            return;
        }

        $selection = session('delivery_slots_selection');
        if (empty($selection) || empty($selection['delivery_slot_id']) || empty($selection['date'])) {
            return;
        }

        $slot = DeliverySlot::query()->find($selection['delivery_slot_id']);
        $slotName = $slot?->name ?? ($selection['slot_name'] ?? 'Preferred Delivery');

        try {
            OrderDeliverySchedule::create([
                'order_id' => $orderId,
                'delivery_slot_id' => $slot?->id,
                'delivery_date' => $selection['date'],
                'slot_name' => $slotName,
                'delivery_instructions' => $selection['instructions'] ?? null,
            ]);

            session()->forget('delivery_slots_selection');
        } catch (\Throwable $e) {
            Log::error('Failed to record delivery schedule for order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

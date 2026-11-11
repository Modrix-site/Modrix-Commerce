<?php

namespace EcoModular\StorePickup\Listeners;

use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderEvent;
use EcoModular\StorePickup\Models\OrderPickup;
use EcoModular\StorePickup\Models\PickupBranch;
use EcoModular\StorePickup\StorePickupSettings;
use EcoModular\StorePickup\StorePickupShippingRateProvider;
use Illuminate\Support\Facades\Log;

class RecordOrderPickup
{
    public function __construct(
        private readonly StorePickupSettings $settings,
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

        if (OrderPickup::query()->where('order_id', $orderId)->exists()) {
            return;
        }

        /** @var Order|null $order */
        $order = Order::query()->find($orderId);
        if (! $order) {
            return;
        }

        $methodCode = (string) $order->shipping_method_code;
        $selection = session('store_pickup_selection');

        $isPickupMethod = $methodCode === StorePickupShippingRateProvider::METHOD_CODE ||
            str_starts_with($methodCode, 'pickup_') ||
            ! empty($selection['branch_id']);

        if (! $isPickupMethod) {
            return;
        }

        // Determine the branch
        $branch = null;
        if (! empty($selection['branch_id'])) {
            $branch = PickupBranch::query()->find($selection['branch_id']);
        } elseif (str_starts_with($methodCode, 'pickup_')) {
            $branchId = (int) substr($methodCode, 7);
            $branch = PickupBranch::query()->find($branchId);
        }

        if (! $branch) {
            $branch = PickupBranch::active()->first();
        }

        if (! $branch) {
            return;
        }

        try {
            OrderPickup::create([
                'order_id' => $orderId,
                'pickup_branch_id' => $branch->id,
                'branch_name' => $branch->name,
                'branch_address' => $branch->fullAddress(),
                'branch_phone' => $branch->phone,
                'pickup_hours' => $branch->pickup_hours ?: 'Standard store hours',
                'collector_name' => $selection['collector_name'] ?? null,
                'collector_phone' => $selection['collector_phone'] ?? null,
                'pickup_date' => $selection['pickup_date'] ?? null,
                'pickup_time' => $selection['pickup_time'] ?? null,
                'customer_notes' => $selection['customer_notes'] ?? null,
                'instructions' => $branch->instructions ?: $this->settings->instructions(),
                'status' => OrderPickup::STATUS_PENDING,
            ]);

            OrderEvent::query()->forceCreate([
                'order_id' => $orderId,
                'type' => 'status',
                'message' => "Order scheduled for In-Store Pickup at {$branch->name}.",
                'is_customer_visible' => true,
            ]);

            // If customer checked out with Cash on Delivery, update order payment details for store pickup
            if ($order->payment_method_code === 'cash_on_delivery') {
                $updates = [];
                if ($this->settings->codBehavior() === StorePickupSettings::COD_PAY_IN_STORE) {
                    $updates['payment_method_name'] = $this->settings->payInStoreLabel();
                }
                if ((float) $order->payment_fee_total > 0 && $this->settings->codBehavior() !== StorePickupSettings::COD_KEEP) {
                    $fee = (float) $order->payment_fee_total;
                    $updates['payment_fee_total'] = '0.0000';
                    $updates['grand_total'] = number_format(max(0, (float) $order->grand_total - $fee), 4, '.', '');
                }
                if (! empty($updates)) {
                    $order->forceFill($updates)->save();
                }
            }

            session()->forget('store_pickup_selection');
        } catch (\Throwable $e) {
            Log::error('Failed to record store pickup for order', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

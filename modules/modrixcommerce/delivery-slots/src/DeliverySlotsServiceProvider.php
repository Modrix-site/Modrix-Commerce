<?php

namespace EcoModular\DeliverySlots;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Checkout\CheckoutValidators;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Models\Order;
use EcoModular\DeliverySlots\Listeners\RecordOrderDeliverySchedule;
use EcoModular\DeliverySlots\Models\OrderDeliverySchedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class DeliverySlotsServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/delivery-slots';

    public function register(): void
    {
        $this->app->singleton(DeliverySlotsSettings::class);
        $this->app->singleton(DeliverySlotResolver::class);
        $this->app->singleton(DeliverySlotCheckoutValidator::class);
    }

    public function boot(StorefrontSlots $slots, CheckoutValidators $validators): void
    {
        // 1. Register checkout validator
        $validators->register($this->app->make(DeliverySlotCheckoutValidator::class));

        // 2. Listen to order completion to record delivery schedules
        Event::listen(CartCheckedOut::class, RecordOrderDeliverySchedule::class);
        Event::listen(OrderPlaced::class, RecordOrderDeliverySchedule::class);

        // 3. Register storefront slot for checkout date/slot picker
        $slots->block(
            self::MODULE,
            'delivery_slot_picker',
            'Delivery Slot Picker',
            'DeliverySlotPicker',
            $places = self::checkoutPlaces(),
            $places[0],
            function () {
                /** @var DeliverySlotsSettings $settings */
                $settings = $this->app->make(DeliverySlotsSettings::class);
                if (! $settings->enabled()) {
                    return null;
                }

                /** @var DeliverySlotResolver $resolver */
                $resolver = $this->app->make(DeliverySlotResolver::class);
                $methodRules = $settings->resolvedMethodRules();
                $maxDaysNeeded = $settings->maxAdvanceDays();
                foreach ($methodRules as $rule) {
                    $maxDaysNeeded = max($maxDaysNeeded, (int) ($rule['max_days'] ?? 2));
                }

                $dates = $resolver->getAvailableDates($maxDaysNeeded);

                $selection = session('delivery_slots_selection');
                $selectedDate = $selection['date'] ?? null;

                // Default to first available date if none selected yet
                if (! $selectedDate && ! empty($dates)) {
                    foreach ($dates as $d) {
                        if ($d['is_available']) {
                            $selectedDate = $d['date'];
                            break;
                        }
                    }
                    if (! $selectedDate) {
                        $selectedDate = $dates[0]['date'] ?? null;
                    }
                }

                $availableSlots = $selectedDate ? $resolver->getSlotsForDate($selectedDate) : [];

                return [
                    'heading' => $settings->heading(),
                    'is_required' => $settings->isRequired(),
                    'default_max_days' => $settings->maxAdvanceDays(),
                    'method_rules' => $methodRules,
                    'dates' => $dates,
                    'initial_slots' => $availableSlots,
                    'initial_date' => $selectedDate,
                    'selection' => $selection,
                    'select_url' => route('modrixcommerce.delivery-slots.select'),
                    'slots_url' => route('modrixcommerce.delivery-slots.slots-for-date'),
                    'clear_url' => route('modrixcommerce.delivery-slots.clear'),
                ];
            }
        );

        // 4. Register delivery summary on order confirmation page
        $slots->register(
            'order.confirmation',
            self::MODULE,
            'DeliveryOrderSummary',
            function (array $context) {
                return $this->resolveOrderSummaryProps($context);
            }
        );

        // 5. Register delivery summary on account order page
        $slots->register(
            'account.order',
            self::MODULE,
            'DeliveryOrderSummary',
            function (array $context) {
                return $this->resolveOrderSummaryProps($context);
            }
        );
    }

    private function resolveOrderSummaryProps(array $context): ?array
    {
        /** @var DeliverySlotsSettings $settings */
        $settings = $this->app->make(DeliverySlotsSettings::class);
        if (! $settings->enabled()) {
            return null;
        }

        $order = $context['order'] ?? null;
        $orderId = null;

        if ($order instanceof Order) {
            $orderId = $order->id;
        } elseif (is_array($order) && isset($order['id'])) {
            $orderId = (int) $order['id'];
        }

        if (! $orderId) {
            return null;
        }

        $schedule = OrderDeliverySchedule::query()
            ->with('slot')
            ->where('order_id', $orderId)
            ->first();

        if (! $schedule) {
            return null;
        }

        $slotWindow = $schedule->slot
            ? substr($schedule->slot->start_time, 0, 5).' - '.substr($schedule->slot->end_time, 0, 5)
            : null;

        return [
            'delivery_date' => $schedule->delivery_date->format('l, F j, Y'),
            'slot_name' => $schedule->slot_name,
            'slot_window' => $slotWindow,
            'instructions' => $schedule->delivery_instructions,
        ];
    }

    /**
     * Checkout places this core can hold, best first. "Under the shipping
     * methods" (checkout.after_shipping) needs a newer core; on an older one
     * the widget falls back to "Above Place order" instead of failing to boot.
     *
     * @return non-empty-list<string>
     */
    private static function checkoutPlaces(): array
    {
        return array_values(array_filter(
            ['checkout.after_shipping', 'checkout.before_place_order', 'checkout.after_summary'],
            fn (string $place): bool => array_key_exists($place, StorefrontSlots::PLACES),
        ));
    }
}

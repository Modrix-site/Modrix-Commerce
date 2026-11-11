<?php

namespace EcoModular\StorePickup;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Checkout\CheckoutValidators;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Models\Order;
use App\Domains\Shipping\ShippingRates;
use EcoModular\StorePickup\Http\Middleware\StorePickupCheckoutMiddleware;
use EcoModular\StorePickup\Listeners\RecordOrderPickup;
use EcoModular\StorePickup\Models\OrderPickup;
use EcoModular\StorePickup\Models\PickupBranch;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class StorePickupServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/store-pickup';

    public function register(): void
    {
        $this->app->singleton(StorePickupSettings::class);
        $this->app->singleton(StorePickupShippingRateProvider::class);
        $this->app->singleton(StorePickupCheckoutValidator::class);
        $this->app->singleton(StorePickupPricingStep::class);
        $this->app->singleton(StorePickupCheckoutMiddleware::class);
    }

    public function boot(StorefrontSlots $slots, CheckoutValidators $validators, ShippingRates $shippingRates, CartPricer $pricer, Router $router): void
    {
        // 1. Register pricing step to waive Cash on Delivery fee when store pickup is active
        $pricer->addStep($this->app->make(StorePickupPricingStep::class), CartPricer::PAYMENT_FEE + 1);

        // 2. Register storefront middleware to dynamically adapt COD to "Pay in store" or hide it
        $router->pushMiddlewareToGroup('web', StorePickupCheckoutMiddleware::class);
        if ($this->app->bound(Kernel::class)) {
            $kernel = $this->app->make(Kernel::class);
            if (method_exists($kernel, 'pushMiddleware')) {
                $kernel->pushMiddleware(StorePickupCheckoutMiddleware::class);
            }
        }

        // 3. Register checkout validator to ensure location selection and valid payment
        $validators->register($this->app->make(StorePickupCheckoutValidator::class));

        // 4. Register shipping rate provider
        $shippingRates->register($this->app->make(StorePickupShippingRateProvider::class));

        // 5. Listen to order completion to record pickup details and update payment title
        Event::listen(CartCheckedOut::class, RecordOrderPickup::class);
        Event::listen(OrderPlaced::class, RecordOrderPickup::class);

        // 6. Register storefront slot for checkout branch selector
        $slots->block(
            self::MODULE,
            'store_pickup_picker',
            'Store & Branch Pickup Selector',
            'StorePickupPicker',
            $places = self::checkoutPlaces(),
            $places[0],
            function () {
                /** @var StorePickupSettings $settings */
                $settings = $this->app->make(StorePickupSettings::class);
                if (! $settings->enabled()) {
                    return null;
                }

                $branches = PickupBranch::active()->get();
                if ($branches->isEmpty()) {
                    return null;
                }

                $selection = session('store_pickup_selection');

                return [
                    'enabled' => true,
                    'shipping_label' => $settings->shippingLabel(),
                    'allow_collector' => $settings->allowAlternativeCollector(),
                    'allow_schedule' => $settings->allowSchedule(),
                    'cod_behavior' => $settings->codBehavior(),
                    'pay_in_store_label' => $settings->payInStoreLabel(),
                    'pay_in_store_instructions' => $settings->payInStoreInstructions(),
                    'branches' => $branches->map(fn (PickupBranch $b) => $b->toPresentable())->all(),
                    'selection' => $selection,
                    'select_url' => route('modrixcommerce.store-pickup.select'),
                    'clear_url' => route('modrixcommerce.store-pickup.clear'),
                    'branches_url' => route('modrixcommerce.store-pickup.branches'),
                ];
            }
        );

        // 5. Register pickup summary on order confirmation page
        $slots->register(
            'order.confirmation',
            self::MODULE,
            'StorePickupOrderSummary',
            function (array $context) {
                return $this->resolveOrderSummaryProps($context);
            }
        );

        // 6. Register pickup summary on customer account order detail page
        $slots->register(
            'account.order',
            self::MODULE,
            'StorePickupOrderSummary',
            function (array $context) {
                return $this->resolveOrderSummaryProps($context);
            }
        );
    }

    private function resolveOrderSummaryProps(array $context): ?array
    {
        $order = $context['order'] ?? null;
        if (! $order instanceof Order) {
            return null;
        }

        $pickup = OrderPickup::query()->where('order_id', $order->id)->first();
        if (! $pickup) {
            return null;
        }

        return [
            'branch_name' => $pickup->branch_name,
            'branch_address' => $pickup->branch_address,
            'branch_phone' => $pickup->branch_phone,
            'pickup_hours' => $pickup->pickup_hours,
            'collector_name' => $pickup->collector_name,
            'collector_phone' => $pickup->collector_phone,
            'pickup_date' => $pickup->pickup_date?->toDateString(),
            'pickup_time' => $pickup->pickup_time,
            'status' => $pickup->status,
            'badge' => $pickup->statusBadge(),
            'ready_at' => $pickup->ready_at?->toIso8601String(),
            'picked_up_at' => $pickup->picked_up_at?->toIso8601String(),
            'instructions' => $pickup->instructions,
            'customer_notes' => $pickup->customer_notes,
            'payment_method_name' => $order->payment_method_name,
            'payment_method_code' => $order->payment_method_code,
            'payment_status' => $order->payment_status->value,
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

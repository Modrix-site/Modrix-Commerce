<?php

namespace EcoModular\CartAbandonmentRecovery;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Customers\PersonalDataExport;
use App\Domains\Orders\Events\CartCheckedOut;
use App\Models\User;
use EcoModular\CartAbandonmentRecovery\Console\ProcessCartRecoveryCommand;
use EcoModular\CartAbandonmentRecovery\Listeners\HandleCartCheckedOut;
use EcoModular\CartAbandonmentRecovery\Models\AbandonedCart;
use EcoModular\CartAbandonmentRecovery\Services\CartRecoveryService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class CartAbandonmentRecoveryServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/cart-abandonment-recovery';

    public function register(): void
    {
        $this->app->singleton(CartRecoveryService::class);
    }

    public function boot(
        StorefrontSlots $slots,
        ModuleSettingsSchema $settings,
        PersonalDataExport $export
    ): void {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'cart-abandonment-recovery');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessCartRecoveryCommand::class,
            ]);

            $this->app->booted(function () {
                $schedule = $this->app->make(Schedule::class);
                $schedule->command('cart-recovery:process')->everyThirtyMinutes()->withoutOverlapping();
            });
        }

        Event::listen(CartCheckedOut::class, HandleCartCheckedOut::class);

        // GDPR Personal Data Export contribution
        $export->contribute('abandoned_carts', fn (User $user) => AbandonedCart::query()
            ->where('user_id', $user->id)
            ->orWhere('email', Str::lower($user->email))
            ->get()
            ->map(fn (AbandonedCart $c) => [
                'email' => $c->email,
                'subtotal' => $c->subtotal,
                'currency' => $c->currency,
                'status' => $c->status,
                'abandoned_at' => $c->abandoned_at->toIso8601String(),
                'recovered_at' => $c->recovered_at?->toIso8601String(),
            ])
            ->all());

        // Register Global Overlay slot for Exit-Intent Modal (wired to all themes)
        $slots->register('storefront.overlay', self::MODULE, 'ExitIntentModal', function () use ($settings) {
            if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
                return null;
            }

            if (! (bool) $settings->of(self::MODULE, 'exit_intent_enabled', true)) {
                return null;
            }

            $discountCode = trim((string) $settings->of(self::MODULE, 'exit_intent_discount_code', ''));

            return [
                'headline' => (string) $settings->of(self::MODULE, 'exit_intent_headline', "Wait! Don't leave your cart behind"),
                'discountCode' => $discountCode !== '' ? $discountCode : null,
                'captureUrl' => route('modrixcommerce.cart-abandonment-recovery.capture-email'),
            ];
        });

        // Register Cart page slot for "Save / Email My Cart" (wired to all themes)
        $slots->register('cart.after_totals', self::MODULE, 'SaveCartWidget', function (array $context) use ($settings) {
            if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
                return null;
            }

            if (! (bool) $settings->of(self::MODULE, 'save_cart_slot_enabled', true)) {
                return null;
            }

            $cart = $context['cart'] ?? null;
            if ($cart === null || (is_object($cart) && method_exists($cart, 'lines') && empty($cart->lines()))) {
                return null;
            }

            return [
                'submitUrl' => route('modrixcommerce.cart-abandonment-recovery.email-cart'),
            ];
        });
    }
}

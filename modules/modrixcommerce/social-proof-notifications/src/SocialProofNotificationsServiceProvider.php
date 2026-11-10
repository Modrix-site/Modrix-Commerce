<?php

namespace EcoModular\SocialProofNotifications;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Events\CartCheckedOut;
use EcoModular\SocialProofNotifications\Listeners\HandleOrderCheckedOut;
use EcoModular\SocialProofNotifications\Services\SocialProofService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class SocialProofNotificationsServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/social-proof-notifications';

    public function register(): void
    {
        $this->app->singleton(SocialProofService::class);
    }

    public function boot(
        StorefrontSlots $slots,
        ModuleSettingsSchema $settings
    ): void {
        Event::listen(CartCheckedOut::class, HandleOrderCheckedOut::class);

        // 1. Global Overlay Slot (wired across all themes and storefront pages)
        $slots->register('storefront.overlay', self::MODULE, 'SocialProofToast', function () use ($settings) {
            if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
                return null;
            }

            /** @var SocialProofService $service */
            $service = $this->app->make(SocialProofService::class);

            return [
                'config' => $service->config(),
                'feedUrl' => route('modrixcommerce.social-proof-notifications.feed'),
                'heartbeatUrl' => route('modrixcommerce.social-proof-notifications.heartbeat'),
                'initialFeed' => $service->getStorefrontFeed(),
            ];
        });

        // 2. Product Details Slot (Live viewers & recent purchases badge on PDP)
        $slots->register('product.after_price', self::MODULE, 'SocialProofProductBadge', function (array $context) use ($settings) {
            if (! (bool) $settings->of(self::MODULE, 'enabled', true)) {
                return null;
            }

            if (! (bool) $settings->of(self::MODULE, 'show_product_badge', true)) {
                return null;
            }

            $product = $context['product'] ?? null;
            if (! ($product instanceof Product)) {
                return null;
            }

            /** @var SocialProofService $service */
            $service = $this->app->make(SocialProofService::class);
            $badge = $service->getProductBadgeData($product);

            if ($badge['recentOrdersCount'] === 0 && $badge['liveViewersCount'] === 0 && ! $badge['isLowStock']) {
                return null;
            }

            return [
                'productId' => $product->id,
                'badge' => $badge,
                'heartbeatUrl' => route('modrixcommerce.social-proof-notifications.heartbeat'),
            ];
        });
    }
}

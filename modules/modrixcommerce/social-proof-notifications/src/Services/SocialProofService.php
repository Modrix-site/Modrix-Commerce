<?php

namespace EcoModular\SocialProofNotifications\Services;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\Models\OrderAddress;
use EcoModular\SocialProofNotifications\Models\LiveProductViewer;
use EcoModular\SocialProofNotifications\Models\SocialProofEvent;
use Illuminate\Support\Collection;

class SocialProofService
{
    public const MODULE = 'modrixcommerce/social-proof-notifications';

    public function __construct(
        protected ModuleSettingsSchema $settings
    ) {}

    /**
     * Check if the module is globally enabled.
     */
    public function isEnabled(): bool
    {
        return (bool) $this->settings->of(self::MODULE, 'enabled', true);
    }

    /**
     * Get module configuration settings.
     *
     * @return array<string, mixed>
     */
    public function config(): array
    {
        return [
            'enabled' => $this->isEnabled(),
            'position' => (string) $this->settings->of(self::MODULE, 'position', 'bottom-left'),
            'themeStyle' => (string) $this->settings->of(self::MODULE, 'theme_style', 'modern_white'),
            'initialDelay' => (int) $this->settings->of(self::MODULE, 'initial_delay_seconds', 3),
            'displayDuration' => (int) $this->settings->of(self::MODULE, 'display_duration_seconds', 5),
            'interval' => (int) $this->settings->of(self::MODULE, 'interval_seconds', 8),
            'maxPerSession' => (int) $this->settings->of(self::MODULE, 'max_per_session', 8),
            'namePrivacy' => (string) $this->settings->of(self::MODULE, 'name_privacy', 'first_name_initial'),
            'showLocation' => (bool) $this->settings->of(self::MODULE, 'show_location', true),
            'hideOnMobile' => (bool) $this->settings->of(self::MODULE, 'hide_on_mobile', false),
            'allowDismiss' => (bool) $this->settings->of(self::MODULE, 'allow_dismiss', true),
            'showProductBadge' => (bool) $this->settings->of(self::MODULE, 'show_product_badge', true),
        ];
    }

    /**
     * Record social proof purchase events from a placed Order.
     */
    public function recordOrder(Order $order): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        /** @var OrderAddress|null $address */
        $address = $order->shippingAddress ?: $order->billingAddress;
        $customerName = null;
        $city = null;
        $country = null;

        if ($address !== null) {
            $customerName = trim("{$address->first_name} {$address->last_name}");
            $city = $address->city;
            $country = $address->country_code;
        } elseif ($order->customer !== null) {
            $customerName = trim("{$order->customer->first_name} {$order->customer->last_name}");
        }

        // Process distinct products purchased in the order
        $order->loadMissing(['items']);
        $processedProducts = [];

        foreach ($order->items as $item) {
            $productId = $item->product_id;
            if ($productId !== null && in_array($productId, $processedProducts, true)) {
                continue;
            }

            if ($productId !== null) {
                $processedProducts[] = $productId;
            }

            $product = $productId !== null ? Product::query()->with('images')->find($productId) : null;
            $productName = ($product instanceof Product) ? $product->localized('name') : $item->product_name;
            $imageUrl = ($product instanceof Product) ? $product->images->first()?->url() : null;
            $productUrl = ($product instanceof Product) ? "/p/{$product->slug}" : null;

            SocialProofEvent::query()->create([
                'event_type' => SocialProofEvent::TYPE_RECENT_PURCHASE,
                'product_id' => $productId,
                'product_name' => $productName,
                'product_image_url' => $imageUrl,
                'product_url' => $productUrl,
                'customer_name' => $customerName ?: 'Verified Buyer',
                'customer_city' => $city,
                'customer_country' => $country,
                'occurred_at' => $order->placed_at,
                'is_active' => true,
                'is_simulated' => false,
            ]);
        }
    }

    /**
     * Get active notification feed for the storefront overlay.
     *
     * @return list<array<string, mixed>>
     */
    public function getStorefrontFeed(?int $currentProductId = null): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $lookupDays = (int) $this->settings->of(self::MODULE, 'purchase_lookup_days', 7);
        $namePrivacy = (string) $this->settings->of(self::MODULE, 'name_privacy', 'first_name_initial');
        $showLocation = (bool) $this->settings->of(self::MODULE, 'show_location', true);
        $limit = (int) $this->settings->of(self::MODULE, 'max_per_session', 8);

        $events = SocialProofEvent::query()
            ->active()
            ->recent($lookupDays)
            ->orderByDesc('occurred_at')
            ->limit($limit)
            ->get();

        // If simulated events are enabled and we have few real events, fallback to starter notifications
        $enableSimulated = (bool) $this->settings->of(self::MODULE, 'enable_simulated_proof', false);
        if ($events->count() < 3 && $enableSimulated) {
            $simulated = $this->generateSimulatedEvents(max(3 - $events->count(), 2));
            $events = $events->concat($simulated);
        }

        // If current product is provided and low stock alert is enabled, prepend urgency toast
        if ($currentProductId !== null && (bool) $this->settings->of(self::MODULE, 'show_low_stock_alerts', true)) {
            $lowStockEvent = $this->buildLowStockEvent($currentProductId);
            if ($lowStockEvent !== null) {
                $events->prepend($lowStockEvent);
            }
        }

        return array_values($events->map(fn (SocialProofEvent $event) => $event->toToastPayload($namePrivacy, $showLocation))->all());
    }

    /**
     * Get social proof badge data for a Product Details Page (PDP).
     *
     * @return array{recentOrdersCount: int, liveViewersCount: int, isLowStock: bool, remainingStock: int|null}
     */
    public function getProductBadgeData(Product $product): array
    {
        $showBadge = (bool) $this->settings->of(self::MODULE, 'show_product_badge', true);
        if (! $this->isEnabled() || ! $showBadge) {
            return [
                'recentOrdersCount' => 0,
                'liveViewersCount' => 0,
                'isLowStock' => false,
                'remainingStock' => null,
            ];
        }

        // 1. Recent orders count in last 48 hours
        $recentOrders = SocialProofEvent::query()
            ->active()
            ->where('product_id', $product->id)
            ->where('event_type', SocialProofEvent::TYPE_RECENT_PURCHASE)
            ->where('occurred_at', '>=', now()->subHours(48))
            ->count();

        // 2. Live viewers in last 5 minutes
        $minViewers = (int) $this->settings->of(self::MODULE, 'min_live_viewers', 2);
        $liveCount = LiveProductViewer::countForProduct($product->id, 5);
        $effectiveViewers = $liveCount >= $minViewers ? $liveCount : 0;

        // 3. Stock urgency check
        $lowStockThreshold = (int) $this->settings->of(self::MODULE, 'low_stock_threshold', 5);
        $totalStock = 0;
        $tracksInventory = false;

        $product->loadMissing(['variants.stocks']);
        foreach ($product->variants as $variant) {
            if ($variant->track_inventory) {
                $tracksInventory = true;
                $totalStock += (int) $variant->stocks->sum('quantity');
            }
        }

        $isLowStock = $tracksInventory && $totalStock > 0 && $totalStock <= $lowStockThreshold;

        return [
            'recentOrdersCount' => $recentOrders,
            'liveViewersCount' => $effectiveViewers,
            'isLowStock' => $isLowStock,
            'remainingStock' => $isLowStock ? $totalStock : null,
        ];
    }

    /**
     * Record impression on an event.
     */
    public function recordImpression(int $eventId): void
    {
        SocialProofEvent::query()
            ->where('id', $eventId)
            ->increment('impressions_count');
    }

    /**
     * Record click on an event.
     */
    public function recordClick(int $eventId): void
    {
        SocialProofEvent::query()
            ->where('id', $eventId)
            ->increment('clicks_count');
    }

    /**
     * Get KPI summary metrics for the admin dashboard.
     *
     * @return array<string, mixed>
     */
    public function getAdminMetrics(): array
    {
        $totalEvents = SocialProofEvent::query()->count();
        $activeEvents = SocialProofEvent::query()->active()->count();
        $totalImpressions = (int) SocialProofEvent::query()->sum('impressions_count');
        $totalClicks = (int) SocialProofEvent::query()->sum('clicks_count');

        $ctr = $totalImpressions > 0
            ? round(($totalClicks / $totalImpressions) * 100, 2)
            : 0.0;

        $recentPurchasesCount = SocialProofEvent::query()
            ->where('event_type', SocialProofEvent::TYPE_RECENT_PURCHASE)
            ->count();

        $activeViewersNow = LiveProductViewer::query()
            ->where('last_seen_at', '>=', now()->subMinutes(5))
            ->distinct('session_id')
            ->count('session_id');

        return [
            'totalEvents' => $totalEvents,
            'activeEvents' => $activeEvents,
            'totalImpressions' => $totalImpressions,
            'totalClicks' => $totalClicks,
            'ctr' => $ctr,
            'recentPurchasesCount' => $recentPurchasesCount,
            'activeViewersNow' => $activeViewersNow,
        ];
    }

    /**
     * Seed sample realistic events for immediate preview.
     */
    public function seedSampleEvents(): int
    {
        $products = Product::query()->where('status', ProductStatus::Published)->with('images')->limit(5)->get();
        if ($products->isEmpty()) {
            return 0;
        }

        $sampleLocations = [
            ['name' => 'Emma Watson', 'city' => 'Austin', 'country' => 'US'],
            ['name' => 'Liam Smith', 'city' => 'London', 'country' => 'GB'],
            ['name' => 'Sophia Müller', 'city' => 'Berlin', 'country' => 'DE'],
            ['name' => 'Lucas Rossi', 'city' => 'Rome', 'country' => 'IT'],
            ['name' => 'Chloe Dubois', 'city' => 'Paris', 'country' => 'FR'],
        ];

        $created = 0;
        foreach ($products as $index => $product) {
            $loc = $sampleLocations[$index % count($sampleLocations)];

            SocialProofEvent::query()->create([
                'event_type' => SocialProofEvent::TYPE_RECENT_PURCHASE,
                'product_id' => $product->id,
                'product_name' => $product->localized('name'),
                'product_image_url' => $product->images->first()?->url(),
                'product_url' => "/p/{$product->slug}",
                'customer_name' => $loc['name'],
                'customer_city' => $loc['city'],
                'customer_country' => $loc['country'],
                'occurred_at' => now()->subMinutes(rand(5, 180)),
                'is_active' => true,
                'is_simulated' => true,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Generate fallback simulated events when real orders are limited.
     *
     * @return Collection<int, SocialProofEvent>
     */
    protected function generateSimulatedEvents(int $count = 3): Collection
    {
        $products = Product::query()->where('status', ProductStatus::Published)->with('images')->inRandomOrder()->limit($count)->get();
        $sampleNames = ['Alex M.', 'Jordan K.', 'Taylor S.', 'Morgan D.', 'Riley W.'];
        $sampleCities = ['New York', 'Toronto', 'Sydney', 'Chicago', 'San Francisco'];

        $collection = collect();
        foreach ($products as $i => $product) {
            $event = new SocialProofEvent([
                'event_type' => SocialProofEvent::TYPE_RECENT_PURCHASE,
                'product_id' => $product->id,
                'product_name' => $product->localized('name'),
                'product_image_url' => $product->images->first()?->url(),
                'product_url' => "/p/{$product->slug}",
                'customer_name' => $sampleNames[$i % count($sampleNames)],
                'customer_city' => $sampleCities[$i % count($sampleCities)],
                'customer_country' => 'US',
                'occurred_at' => now()->subMinutes(rand(8, 90)),
                'is_active' => true,
                'is_simulated' => true,
            ]);
            // `id` is guarded; these unsaved events still need a stable key for the storefront.
            $event->id = 900000 + $product->id + $i;

            $collection->push($event);
        }

        return $collection;
    }

    /**
     * Build low stock alert event for a product if threshold met.
     */
    protected function buildLowStockEvent(int $productId): ?SocialProofEvent
    {
        $product = Product::query()->with('variants.stocks')->find($productId);
        if ($product === null) {
            return null;
        }

        $threshold = (int) $this->settings->of(self::MODULE, 'low_stock_threshold', 5);
        $totalStock = 0;
        $tracks = false;

        foreach ($product->variants as $variant) {
            if ($variant->track_inventory) {
                $tracks = true;
                $totalStock += (int) $variant->stocks->sum('quantity');
            }
        }

        if (! $tracks || $totalStock <= 0 || $totalStock > $threshold) {
            return null;
        }

        $event = new SocialProofEvent([
            'event_type' => SocialProofEvent::TYPE_LOW_STOCK,
            'product_id' => $product->id,
            'product_name' => $product->localized('name'),
            'product_image_url' => $product->images->first()?->url(),
            'product_url' => "/p/{$product->slug}",
            'message' => "Only {$totalStock} items left in stock — selling fast!",
            'occurred_at' => now(),
            'is_active' => true,
            'is_simulated' => false,
        ]);
        $event->id = 800000 + $productId;

        return $event;
    }
}

<?php

namespace EcoModular\MultiVendor;

use App\Core\Catalog\ProductLabels;
use App\Core\Extensions\DashboardWidgets;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\StorefrontCatalog;
use App\Domains\Orders\Events\OrderPlaced;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use EcoModular\MultiVendor\Models\MarketplaceVendor;
use EcoModular\MultiVendor\Models\MarketplaceVendorOrder;
use EcoModular\MultiVendor\Models\MarketplaceVendorProduct;
use EcoModular\MultiVendor\Services\MultiVendorService;
use EcoModular\MultiVendor\Support\MultiVendorSettings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Throwable;

class MultiVendorServiceProvider extends ServiceProvider
{
    public const MODULE = 'modrixcommerce/multi-vendor';

    public const ADMIN_ROUTE_PREFIX = 'admin.modrixcommerce.multi-vendor.';

    public const STOREFRONT_ROUTE_PREFIX = 'modrixcommerce.multi-vendor.';

    public function register(): void
    {
        $this->app->scoped(MultiVendorSettings::class);
        $this->app->scoped(MultiVendorService::class);
    }

    public function boot(
        StorefrontSlots $slots,
        ProductLabels $labels,
        DashboardWidgets $widgets,
    ): void {
        $this->registerProductCardLabels($labels);
        $this->registerStorefrontAndAdminSlots($slots);
        $this->registerDashboardWidget($widgets);
        $this->registerEventListeners();
    }

    /**
     * Injects vendor badges into product cards across every storefront theme.
     */
    private function registerProductCardLabels(ProductLabels $labels): void
    {
        $labels->register(self::MODULE, function (mixed $product): array {
            try {
                if (! $product instanceof Product) {
                    return [];
                }

                $settings = $this->app->make(MultiVendorSettings::class);
                if (! $settings->enabled() || ! $settings->showVendorOnCards()) {
                    return [];
                }

                $summary = $this->app->make(MultiVendorService::class)->vendorSummaryForProduct($product->id);
                if ($summary === null) {
                    return [];
                }

                $prefix = $summary['is_verified'] ? '✓ ' : 'By ';

                return [
                    [
                        'key' => 'vendor-'.$summary['vendor_id'],
                        'text' => mb_substr($prefix.$summary['store_name'], 0, 38),
                        'tone' => $summary['is_verified'] ? 'featured' : 'info',
                        'position' => 'bottom-left',
                        'style' => 'soft',
                    ],
                ];
            } catch (Throwable $e) {
                report($e);

                return [];
            }
        });
    }

    /**
     * Wires Multi-Vendor components into Storefront product pages, home/catalog pages,
     * order confirmation/account pages, footer, and the Admin Product Editor sidebar.
     */
    private function registerStorefrontAndAdminSlots(StorefrontSlots $slots): void
    {
        // 1. Product Page Seller Info & Verified Business Box
        $slots->register('product.after_details', self::MODULE, 'ProductVendorBoxSlot', function (array $context) {
            $product = $context['product'] ?? null;
            if (! $product instanceof Product) {
                return null;
            }

            $settings = $this->app->make(MultiVendorSettings::class);
            if (! $settings->enabled() || ! $settings->showVendorBoxOnProduct()) {
                return null;
            }

            $vp = MarketplaceVendorProduct::query()
                ->where('product_id', $product->id)
                ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                ->with('vendor')
                ->first();

            if (! $vp instanceof MarketplaceVendorProduct || ! $vp->vendor instanceof MarketplaceVendor || ! $vp->vendor->isApproved()) {
                return null;
            }

            $vendor = $vp->vendor;
            $productsCount = MarketplaceVendorProduct::query()
                ->where('vendor_id', $vendor->id)
                ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                ->count();

            return [
                'vendor' => [
                    'id' => $vendor->id,
                    'store_name' => $vendor->store_name,
                    'slug' => $vendor->slug,
                    'tagline' => $vendor->tagline,
                    'city' => $vendor->city,
                    'country_code' => $vendor->country_code,
                    'is_verified' => $vendor->isVerified(),
                    'legal_business_name' => $vendor->legal_business_name,
                    'rating' => (float) $vendor->rating,
                    'products_count' => $productsCount,
                    'store_url' => route(self::STOREFRONT_ROUTE_PREFIX.'vendors.show', $vendor->slug),
                ],
                'catalogUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'catalog'),
                'directoryUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'vendors.index'),
            ];
        }, 35);

        // 2. Movable Storefront Block: Featured Marketplace Vendors & Live Picks (Home / Catalog)
        $slots->block(
            self::MODULE,
            'featured-vendors',
            'Featured marketplace sellers & catalog',
            'FeaturedVendorsBlock',
            ['home.after_featured', 'catalog.before_products', 'catalog.after_products'],
            'home.after_featured',
            function () {
                $settings = $this->app->make(MultiVendorSettings::class);
                if (! $settings->enabled()) {
                    return null;
                }

                $vendors = MarketplaceVendor::query()
                    ->where('status', MarketplaceVendor::STATUS_APPROVED)
                    ->withCount([
                        'vendorProducts as approved_products_count' => fn ($q) => $q->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED),
                    ])
                    ->orderByDesc('is_featured')
                    ->orderByDesc('id')
                    ->limit(6)
                    ->get()
                    ->map(fn (MarketplaceVendor $v) => [
                        'id' => $v->id,
                        'store_name' => $v->store_name,
                        'slug' => $v->slug,
                        'tagline' => $v->tagline,
                        'city' => $v->city,
                        'country_code' => $v->country_code,
                        'is_verified' => $v->isVerified(),
                        'rating' => (float) $v->rating,
                        'products_count' => $v->approved_products_count,
                        'url' => route(self::STOREFRONT_ROUTE_PREFIX.'vendors.show', $v->slug),
                    ])
                    ->values()
                    ->all();

                $recentVendorProductIds = array_values(array_map(
                    'intval',
                    MarketplaceVendorProduct::query()
                        ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                        ->whereHas('vendor', fn ($q) => $q->where('status', MarketplaceVendor::STATUS_APPROVED))
                        ->orderByDesc('id')
                        ->limit(12)
                        ->pluck('product_id')
                        ->all()
                ));

                $featuredProducts = [];
                $productVendors = [];
                if ($recentVendorProductIds !== []) {
                    $currency = $this->app->make(StoreSettings::class)->currency();
                    $featuredProducts = $this->app->make(StorefrontCatalog::class)->cardsFor($recentVendorProductIds, $currency, 8);
                    $productVendors = $this->app->make(MultiVendorService::class)->productMarketplaceMeta($recentVendorProductIds)['vendors'];
                }

                if ($vendors === [] && $featuredProducts === [] && ! $settings->allowVendorRegistration()) {
                    return null;
                }

                return [
                    'vendors' => $vendors,
                    'featuredProducts' => $featuredProducts,
                    'productVendors' => $productVendors,
                    'allowRegistration' => $settings->allowVendorRegistration(),
                    'catalogUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'catalog'),
                    'directoryUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'vendors.index'),
                    'registerUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'register'),
                ];
            },
            50
        );

        // 2b. Movable Storefront Block: Amazon-Style Marketplace Catalog Explorer Strip (Core Category/Search)
        $slots->block(
            self::MODULE,
            'marketplace-catalog-strip',
            'Marketplace catalog & seller filter bar',
            'MarketplaceCatalogStripSlot',
            ['catalog.before_products', 'catalog.after_products', 'home.after_featured'],
            'catalog.before_products',
            function () {
                $settings = $this->app->make(MultiVendorSettings::class);
                if (! $settings->enabled()) {
                    return null;
                }

                $sellersCount = MarketplaceVendor::query()
                    ->where('status', MarketplaceVendor::STATUS_APPROVED)
                    ->count();
                $verifiedSellersCount = MarketplaceVendor::query()
                    ->where('status', MarketplaceVendor::STATUS_APPROVED)
                    ->where('verification_status', MarketplaceVendor::VERIFICATION_VERIFIED)
                    ->count();
                $vendorProductsCount = MarketplaceVendorProduct::query()
                    ->where('approval_status', MarketplaceVendorProduct::STATUS_APPROVED)
                    ->count();

                if ($sellersCount === 0 && ! $settings->allowVendorRegistration()) {
                    return null;
                }

                return [
                    'sellersCount' => $sellersCount,
                    'verifiedSellersCount' => $verifiedSellersCount,
                    'vendorProductsCount' => $vendorProductsCount,
                    'catalogUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'catalog'),
                    'directoryUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'vendors.index'),
                    'registerUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'register'),
                    'allowRegistration' => $settings->allowVendorRegistration(),
                ];
            },
            25
        );

        // 3. Order Confirmation & Customer Account Order Page: Vendor Fulfillment Breakdown
        $orderSlotBuilder = function (array $context) {
            $order = $context['order'] ?? null;
            if (! $order instanceof Order) {
                return null;
            }

            $settings = $this->app->make(MultiVendorSettings::class);
            if (! $settings->enabled()) {
                return null;
            }

            $vendorOrders = MarketplaceVendorOrder::query()
                ->where('order_id', $order->id)
                ->with('vendor')
                ->get();

            if ($vendorOrders->isEmpty()) {
                return null;
            }

            return [
                'items' => $vendorOrders->map(fn (MarketplaceVendorOrder $vo) => [
                    'id' => $vo->id,
                    'product_name' => $vo->product_name,
                    'quantity' => $vo->quantity,
                    'vendor_name' => $vo->vendor?->store_name ?? 'Marketplace Seller',
                    'vendor_verified' => $vo->vendor?->isVerified() ?? false,
                    'vendor_url' => $vo->vendor
                        ? route(self::STOREFRONT_ROUTE_PREFIX.'vendors.show', $vo->vendor->slug)
                        : null,
                    'fulfillment_status' => $vo->fulfillment_status,
                    'tracking_number' => $vo->tracking_number,
                ])->values()->all(),
            ];
        };

        $slots->register('order.confirmation', self::MODULE, 'OrderVendorsSlot', $orderSlotBuilder, 40);
        $slots->register('account.order', self::MODULE, 'OrderVendorsSlot', $orderSlotBuilder, 40);

        // 4. Storefront Footer: Marketplace Vendor Links (Browse Sellers / Become a Seller / Seller Portal)
        $slots->register('storefront.footer', self::MODULE, 'MarketplaceFooterLinksSlot', function () {
            $settings = $this->app->make(MultiVendorSettings::class);
            if (! $settings->enabled()) {
                return null;
            }

            return [
                'allowRegistration' => $settings->allowVendorRegistration(),
                'catalogUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'catalog'),
                'directoryUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'vendors.index'),
                'registerUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'register'),
                'loginUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'login'),
                'dashboardUrl' => route(self::STOREFRONT_ROUTE_PREFIX.'dashboard'),
            ];
        }, 60);

        // 5. Admin Product Editor Sidebar Panel: Assign Vendor & Commission Override
        $slots->register('admin.product.sidebar', self::MODULE, 'AdminProductVendorPanel', function (array $context) {
            $product = $context['product'] ?? null;
            $user = auth()->user();

            if (! $product instanceof Product || ! $user instanceof User || ! $user->can('multi_vendor.manage')) {
                return null;
            }

            $vp = MarketplaceVendorProduct::query()
                ->where('product_id', $product->id)
                ->with('vendor')
                ->first();

            $vendors = MarketplaceVendor::query()
                ->orderBy('store_name')
                ->get(['id', 'store_name', 'status', 'verification_status', 'commission_rate'])
                ->map(fn (MarketplaceVendor $v) => [
                    'id' => $v->id,
                    'store_name' => $v->store_name,
                    'status' => $v->status,
                    'is_verified' => $v->isVerified(),
                    'commission_rate' => $v->commission_rate !== null ? (float) $v->commission_rate : null,
                ])
                ->values()
                ->all();

            return [
                'productId' => $product->id,
                'defaultCommissionRate' => $this->app->make(MultiVendorSettings::class)->defaultCommissionRate(),
                'assignment' => $vp ? [
                    'id' => $vp->id,
                    'vendor_id' => $vp->vendor_id,
                    'vendor_name' => $vp->vendor?->store_name,
                    'vendor_sku' => $vp->vendor_sku,
                    'approval_status' => $vp->approval_status,
                    'commission_override' => $vp->commission_override !== null ? (float) $vp->commission_override : null,
                ] : null,
                'vendors' => $vendors,
                'assignUrl' => route(self::ADMIN_ROUTE_PREFIX.'products.assign'),
                'dashboardUrl' => route(self::ADMIN_ROUTE_PREFIX.'index'),
            ];
        });
    }

    /**
     * Registers the Multi-Vendor Marketplace summary widget on the main Admin Dashboard.
     */
    private function registerDashboardWidget(DashboardWidgets $widgets): void
    {
        $widgets->register(
            'overview',
            self::MODULE,
            'Multi-Vendor Marketplace',
            'MultiVendorOverviewWidget',
            'multi_vendor.manage',
            function () {
                $service = $this->app->make(MultiVendorService::class);
                $currency = $this->app->make(StoreSettings::class)->currency();

                return [
                    'stats' => $service->stats(),
                    'currency' => $currency,
                    'dashboardUrl' => route(self::ADMIN_ROUTE_PREFIX.'index'),
                ];
            },
            45
        );
    }

    /**
     * Listens to OrderPlaced to automatically split vendor orders, calculate commissions,
     * credit vendor balances, and trigger automatic payouts when configured.
     */
    private function registerEventListeners(): void
    {
        Event::listen(OrderPlaced::class, function (OrderPlaced $event): void {
            try {
                $order = Order::query()->with('items')->find($event->orderId);
                if ($order instanceof Order) {
                    $this->app->make(MultiVendorService::class)->recordOrderCommissions($order);
                }
            } catch (Throwable $e) {
                report($e);
            }
        });

        Product::deleting(function (Product $product): void {
            try {
                MarketplaceVendorProduct::query()->where('product_id', $product->getKey())->delete();
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}

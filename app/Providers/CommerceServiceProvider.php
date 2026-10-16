<?php

namespace App\Providers;

use App\Core\Admin\AdminNavigation;
use App\Core\Settings\SettingsRepository;
use App\Domains\Cart\Listeners\MergeGuestCartOnLogin;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Checkout\Pricing\Steps\DiscountStep;
use App\Domains\Checkout\Pricing\Steps\ShippingStep;
use App\Domains\Checkout\Pricing\Steps\TaxStep;
use App\Domains\Customers\Listeners\CreateCustomerProfile;
use App\Domains\Customers\Listeners\LinkGuestOrdersOnVerification;
use App\Domains\Customers\PersonalDataExport;
use App\Domains\Downloads\Listeners\GrantDownloads;
use App\Domains\Downloads\Listeners\RevokeDownloads;
use App\Domains\Inventory\Events\InventoryLow;
use App\Domains\Inventory\Listeners\SendLowStockAlert;
use App\Domains\Orders\Events\OrderCancelled;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Events\OrderRefunded;
use App\Domains\Orders\Listeners\IssueInvoiceOnPayment;
use App\Domains\Orders\Listeners\SendOrderEmails;
use App\Domains\Payments\Gateways\Stripe\StripeGateway;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Shipping\ShippingRates;
use App\Domains\Shipping\TableShippingRateProvider;
use App\Domains\Tax\Contracts\TaxRateProvider;
use App\Domains\Tax\TableTaxRateProvider;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the commerce domains: pricing pipeline, extension registries,
 * listeners and admin navigation.
 */
class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Modules add their own personal data to customer exports.
        $this->app->singleton(PersonalDataExport::class);

        $this->app->singleton(ShippingRates::class, function () {
            $rates = new ShippingRates;
            $rates->register(new TableShippingRateProvider);

            return $rates;
        });

        $this->app->singleton(PaymentMethods::class, function ($app) {
            $methods = new PaymentMethods($app->make(SettingsRepository::class));
            // Offered at checkout only once enabled with credentials (Admin → Payments → Stripe).
            $methods->register($app->make(StripeGateway::class));

            return $methods;
        });

        // Modules may rebind this to a provider for their jurisdiction.
        $this->app->bind(TaxRateProvider::class, TableTaxRateProvider::class);

        $this->app->singleton(CartPricer::class, function ($app) {
            $pricer = new CartPricer;
            // Order matters: discounts reduce the taxable base and free-shipping thresholds.
            $pricer->addStep($app->make(DiscountStep::class));
            $pricer->addStep($app->make(ShippingStep::class));
            $pricer->addStep($app->make(TaxStep::class));

            return $pricer;
        });

        // Commerce contributes its admin pages the same way a module would.
        $this->app->extend(AdminNavigation::class, function (AdminNavigation $navigation) {
            $navigation->add('orders', 'Orders', 'admin.orders.index', 'receipt', 'Sales', 'orders.view', 50);
            $navigation->add('customers', 'Customers', 'admin.customers.index', 'users', 'Sales', 'customers.view', 60);
            $navigation->add('discounts', 'Discounts', 'admin.coupons.index', 'percent', 'Sales', 'discounts.view', 70);
            $navigation->add('reports', 'Reports', 'admin.reports.index', 'chart-column', 'Sales', 'reports.view', 80);
            $navigation->add('products', 'Products', 'admin.products.index', 'package', 'Catalog', 'products.view', 100);
            $navigation->add('categories', 'Categories', 'admin.categories.index', 'folder-tree', 'Catalog', 'products.update', 110);
            $navigation->add('brands', 'Brands', 'admin.brands.index', 'tags', 'Catalog', 'products.update', 115);
            $navigation->add('translations', 'Translations', 'admin.translations.index', 'languages', 'Catalog', 'products.update', 115);
            $navigation->add('media', 'Media library', 'admin.media.index', 'image', 'Catalog', 'media.manage', 116);
            $navigation->add('attributes', 'Options', 'admin.attributes.index', 'tags', 'Catalog', 'products.update', 120);
            $navigation->add('inventory', 'Inventory', 'admin.inventory.index', 'warehouse', 'Catalog', 'inventory.view', 130);
            $navigation->add('shipping', 'Shipping', 'admin.shipping-methods.index', 'truck', 'Configuration', 'shipping.manage', 800);
            $navigation->add('taxes', 'Taxes', 'admin.tax-rates.index', 'percent', 'Configuration', 'settings.manage', 810);
            $navigation->add('payments', 'Payments', 'admin.payments.settings', 'credit-card', 'Configuration', 'payments.manage', 820);

            return $navigation;
        });
    }

    public function boot(): void
    {
        Event::listen(Registered::class, CreateCustomerProfile::class);
        Event::listen(Verified::class, LinkGuestOrdersOnVerification::class);
        Event::listen(Login::class, MergeGuestCartOnLogin::class);
        Event::listen(InventoryLow::class, SendLowStockAlert::class);
        Event::listen(OrderPaid::class, IssueInvoiceOnPayment::class);
        // Digital products: download links on payment, revoked on cancel or full refund.
        Event::listen(OrderPaid::class, GrantDownloads::class);
        Event::listen(OrderCancelled::class, RevokeDownloads::class);
        Event::listen(OrderRefunded::class, RevokeDownloads::class);
        Event::subscribe(SendOrderEmails::class);

        RateLimiter::for('checkout', fn (Request $request) => [
            Limit::perMinute(10)->by('checkout:'.($request->user()?->getAuthIdentifier() ?? $request->session()->getId())),
            Limit::perMinute(30)->by('checkout-ip:'.$request->ip()),
        ]);

        // Generous: providers send bursts and retry anything that is refused.
        // Merchant stores call the marketplace API a few times a day; generous, per IP.
        RateLimiter::for('marketplace-api', fn (Request $request) => Limit::perMinute(60)->by('marketplace:'.$request->ip()));

        RateLimiter::for('webhooks', fn (Request $request) => Limit::perMinute(600)->by('webhook:'.$request->ip()));
    }
}

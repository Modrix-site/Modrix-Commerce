<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\ProductVariant;
use EcoModular\CurrencyConverter\LiveRates;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * A live rate turns the store price into the currency the shopper picked.
 * Checkout shows the currency the shopper picked. The saved order stays in the store currency.
 */
class CurrencyConverterTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
        $this->setUpModules();
        File::copyDirectory(base_path('modules/modrixcommerce/currency-converter'), $this->modulesPath.'/modrixcommerce/currency-converter');
        app(ModuleRepository::class)->sync();
        app(ModuleManager::class)->install('modrixcommerce/currency-converter');
        app(ModuleManager::class)->enable('modrixcommerce/currency-converter', true);
        app(SettingsRepository::class)->set('store.default_currency', 'USD');
        $this->enableCheckout();
        $this->variant = $this->sellable('10.00', 5);
        app(SettingsRepository::class)->set('store.display_currencies', [
            ['code' => 'AED', 'rate' => '3.67'],
            ['code' => 'EUR', 'rate' => '0.92'],
        ]);
    }

    public function test_a_live_rate_converts_the_price_when_the_shopper_changes_currency(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['AED' => 3.6725, 'EUR' => 0.92],
            ]),
        ]);

        $this->get('/p/'.$this->variant->product->slug.'?currency=AED')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.displayCurrency', 'AED')
            ->where('storefront.currencies.1.code', 'AED')
            ->where('product.variants.0.price.currency', 'USD')
            ->where('product.variants.0.price.amount', '10.00')
            ->where('product.variants.0.price.display_currency', 'AED')
            ->where('product.variants.0.price.display_amount', '36.73'));

        $this->get('/p/'.$this->variant->product->slug.'?currency=AED')->assertOk();
        Http::assertSentCount(1);

        $this->addToCart($this->variant);
        $this->get('/checkout')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.displayCurrency', 'AED')
            ->where('storefront.canSwitchCurrency', true)
            ->where('summary.currency', 'USD')
            ->where('summary.lines.0.subtotal.amount', '10.00')
            ->where('summary.lines.0.subtotal.currency', 'USD')
            ->where('summary.lines.0.subtotal.display_currency', 'AED')
            ->where('summary.lines.0.subtotal.display_amount', '36.73'));
    }

    public function test_only_the_currencies_in_the_setting_are_offered(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response([
                'result' => 'success',
                'base_code' => 'USD',
                'rates' => ['AED' => 3.67, 'EUR' => 0.92],
            ]),
        ]);

        app(SettingsRepository::class)->set('store.display_currencies', [['code' => 'EUR', 'rate' => '0.92']]);
        app(ModuleManager::class)->configure('modrixcommerce/currency-converter', [
            'currencies' => 'EUR',
            'refresh_hours' => 6,
        ]);

        $this->get('/?currency=AED')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.displayCurrency', 'USD')
            ->where('storefront.currencies', fn ($currencies) => collect($currencies)->pluck('code')->all() === ['USD', 'EUR']));
        app(SettingsRepository::class)->set('store.display_currencies', []);
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.currencies', fn ($currencies) => collect($currencies)->pluck('code')->all() === ['USD']));
    }

    public function test_a_failed_rate_lookup_leaves_prices_in_the_store_currency(): void
    {
        Http::fake([
            'open.er-api.com/*' => Http::response(['result' => 'error'], 500),
        ]);

        $this->get('/p/'.$this->variant->product->slug.'?currency=AED')->assertInertia(fn (Assert $page) => $page
            ->where('storefront.displayCurrency', 'AED')
            ->where('product.variants.0.price.display_currency', 'AED')
            ->where('product.variants.0.price.display_amount', '36.70'));
        $this->get('/')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_failed_refresh_keeps_stale_rates_and_recovers_after_the_cooldown(): void
    {
        Cache::forever(LiveRates::CACHE, [
            'base' => 'USD', 'fetched_at' => now()->subDay()->timestamp, 'rates' => ['AED' => '3.68'],
        ]);
        Http::fakeSequence()->push(['result' => 'error'], 503)->push(['result' => 'success', 'rates' => ['AED' => 3.69]]);
        $this->get('/p/'.$this->variant->product->slug.'?currency=AED')->assertInertia(fn (Assert $page) => $page->where('product.variants.0.price.display_amount', '36.80'));
        $this->get('/?currency=AED')->assertOk();
        Http::assertSentCount(1);
        $this->travel(61)->seconds();
        // The page shows the stale rate at once; the refresh runs after the response.
        $this->get('/p/'.$this->variant->product->slug.'?currency=AED')->assertInertia(fn (Assert $page) => $page->where('product.variants.0.price.display_amount', '36.80'));
        Http::assertSentCount(2);
        $this->get('/p/'.$this->variant->product->slug.'?currency=AED')->assertInertia(fn (Assert $page) => $page->where('product.variants.0.price.display_amount', '36.90'));
        Http::assertSentCount(2);
    }

    /** A slow or unreachable rates API must never hold up a page that already has rates. */
    public function test_stale_rates_render_without_waiting_for_the_rates_api(): void
    {
        Cache::forever(LiveRates::CACHE, [
            'base' => 'USD', 'fetched_at' => now()->subDay()->timestamp, 'rates' => ['AED' => '3.68'],
        ]);
        $handled = false;
        $sentBeforeResponse = null;
        Event::listen(RequestHandled::class, function () use (&$handled) {
            $handled = true;
        });
        Http::fake(function () use (&$handled, &$sentBeforeResponse) {
            $sentBeforeResponse ??= ! $handled;

            return Http::response(['result' => 'success', 'rates' => ['AED' => 3.69]]);
        });

        $this->get('/p/'.$this->variant->product->slug.'?currency=AED')
            ->assertInertia(fn (Assert $page) => $page->where('product.variants.0.price.display_amount', '36.80'));

        Http::assertSentCount(1);
        $this->assertFalse($sentBeforeResponse, 'The rates API was called before the page was sent.');
    }
}

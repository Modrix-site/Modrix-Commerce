<?php

namespace Tests\Feature\Storefront;

use App\Core\Localization\Locales;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StorefrontCurrencies;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class InternationalStoreSettingsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        config(['inertia.ssr.enabled' => false]);
    }

    private function settings(): array
    {
        return ['store_name' => 'International Store', 'default_currency' => 'USD', 'timezone' => 'UTC', 'weight_unit' => 'kg', 'dimension_unit' => 'cm', 'display_currencies' => [['code' => 'EUR', 'rate' => '0.9'], ['code' => 'KWD', 'rate' => '0.3']], 'profile' => ['phone' => '+123456', 'meta_title' => 'Global shop', 'meta_description' => 'Shop around the world'], 'language_default' => 'en', 'language_additional' => ['ar', 'hi', 'ur', 'es']];
    }

    public function test_settings_save_currency_rates_languages_and_metadata(): void
    {
        $this->actingAs($this->userWithRole('administrator'))->put(route('admin.settings.store.update'), $this->settings())->assertSessionHasNoErrors();
        $this->assertTrue(app(Locales::class)->isEnabled('ar'));
        $this->assertTrue(app(Locales::class)->isEnabled('hi'));
        $this->assertSame('+123456', app(SettingsRepository::class)->get('store.phone'));
        $this->assertSame('9.00', app(StorefrontCurrencies::class)->display(Money::of('10', 'USD'), 'EUR')->toDisplayDecimal());
        $this->assertSame('3.000', app(StorefrontCurrencies::class)->display(Money::of('10', 'USD'), 'KWD')->toDisplayDecimal());
        $this->assertGreaterThanOrEqual(180, count(Locales::AVAILABLE));
        $this->get('/?lang=ar')->assertInertia(fn (Assert $p) => $p->where('storefront.direction', 'rtl')->where('storefront.locale', 'ar')->where('storefront.seoTitle', 'Global shop'));
    }

    public function test_display_currency_changes_checkout_prices_without_changing_the_order_currency(): void
    {
        $this->enableCheckout();
        app(SettingsRepository::class)->set('store.display_currencies', [['code' => 'EUR', 'rate' => '0.9']]);
        $variant = $this->sellable('10.00', 5);
        $this->addToCart($variant);
        $this->get('/p/'.$variant->product->slug.'?currency=EUR')->assertInertia(fn (Assert $p) => $p->where('product.variants.0.price.amount', '10.00')->where('product.variants.0.price.currency', 'USD')->where('product.variants.0.price.display_currency', 'EUR')->where('product.variants.0.price.display_amount', '9.00')->where('storefront.displayCurrency', 'EUR'));
        $this->get('/search?currency=EUR&max_price=9')->assertInertia(fn (Assert $p) => $p->has('products.data', 1));
        $this->get('/cart')->assertInertia(fn (Assert $p) => $p->where('storefront.displayCurrency', 'EUR'));
        $this->get('/checkout')->assertInertia(fn (Assert $p) => $p->where('storefront.displayCurrency', 'EUR')->where('storefront.canSwitchCurrency', true));
        $this->assertSame('KWD', Money::of('3', 'KWD')->jsonSerialize()['display_currency']);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $this->assertSame('USD', Order::firstOrFail()->currency);
        $this->get('/?currency=XXX')->assertInertia(fn (Assert $p) => $p->where('storefront.displayCurrency', 'USD'));
    }

    public function test_invalid_rates_are_rejected_before_settings_change(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        $data = $this->settings();
        $data['display_currencies'][0]['rate'] = '0';
        $this->put(route('admin.settings.store.update'), $data)->assertSessionHasErrors('display_currencies.0.rate');
        $data = $this->settings();
        $data['display_currencies'][1]['code'] = 'EUR';
        $this->put(route('admin.settings.store.update'), $data)->assertSessionHasErrors('display_currencies.0.code');
        $this->assertNull(app(SettingsRepository::class)->get('store.phone'));
    }
}

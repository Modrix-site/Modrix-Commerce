<?php

namespace Tests\Feature\Shipping;

use App\Core\Settings\SettingsRepository;
use App\Domains\Orders\Models\Order;
use App\Domains\Pricing\Money;
use App\Domains\Shipping\Contracts\ShippingRateProvider;
use App\Domains\Shipping\DeliveryArea;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Domains\Shipping\ShippingCoverage;
use App\Domains\Shipping\ShippingRate;
use App\Domains\Shipping\ShippingRates;
use App\Domains\Shipping\ShippingRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class DeliveryAreaTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Mail::fake();
    }

    private function settings(array $delivery, array $location = []): array
    {
        return [
            'store_name' => 'Local store', 'default_currency' => 'USD', 'timezone' => 'UTC', 'weight_unit' => 'kg', 'dimension_unit' => 'cm',
            'profile' => ['country' => 'US', 'region' => 'Illinois', 'city' => 'Springfield', ...$location],
            'delivery' => $delivery,
        ];
    }

    private function configure(array $delivery, array $location = []): void
    {
        $this->actingAs($this->userWithRole('administrator'))
            ->put(route('admin.settings.store.update'), $this->settings($delivery, $location))->assertSessionHasNoErrors();
        auth()->logout();
    }

    public function test_store_location_and_single_country_delivery_use_the_existing_business_country(): void
    {
        $this->configure(['mode' => 'store_country']);
        $this->assertSame('US', app(SettingsRepository::class)->get('store.country'));
        $this->assertSame('Springfield', app(SettingsRepository::class)->get('store.city'));
        $this->assertSame(['US'], app(ShippingCoverage::class)->codes('USD'));

        $this->addToCart($this->sellable());
        $this->get(route('checkout.show'))->assertInertia(fn (Assert $page) => $page
            ->has('countries', 1)
            ->where('soleCountry', 'US')
            ->where('deliveryRestriction', 'United States'));
        $this->post(route('checkout.store'), $this->checkoutPayload(['shipping' => ['country_code' => 'PH', 'region' => '']]))->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_multiple_delivery_countries_are_intersected_with_shipping_coverage(): void
    {
        $this->configure(['mode' => 'selected_countries', 'countries' => ['US', 'PH', 'AE']]);
        $this->assertEqualsCanonicalizing(['US', 'PH'], app(ShippingCoverage::class)->codes('USD'));
        ShippingMethod::query()->update(['countries' => null]);
        $this->assertEqualsCanonicalizing(['US', 'PH', 'AE'], app(ShippingCoverage::class)->codes('USD'));
        $this->assertFalse(app(DeliveryArea::class)->allows('GB'));
        $this->assertTrue(app(DeliveryArea::class)->allows('PH'));
    }

    public function test_all_shipping_providers_respect_the_store_boundary(): void
    {
        $this->configure(['mode' => 'store_country']);
        $rates = new ShippingRates;
        $rates->register(new class implements ShippingRateProvider
        {
            public function rates(ShippingRequest $request): array
            {
                return [new ShippingRate('carrier', 'Carrier delivery', Money::of('1', $request->currency))];
            }
        });
        $request = fn (string $country) => new ShippingRequest('USD', $country, null, null, Money::of('20', 'USD'), '0', 1);
        $this->assertSame([], $rates->quote($request('PH')));
        $this->assertCount(1, $rates->quote($request('US')));
    }

    public function test_region_delivery_matches_aliases_and_refuses_other_regions(): void
    {
        $this->configure(['mode' => 'store_region'], ['country' => 'AE', 'region' => 'Dubai']);
        $area = app(DeliveryArea::class);
        $this->assertTrue($area->allows('AE', 'AE-DU'));
        $this->assertTrue($area->allows('AE', ' dubai '));
        $this->assertFalse($area->allows('AE', 'Sharjah'));
        $this->assertFalse($area->allows('AE'));
        $this->assertFalse($area->allows('US', 'Dubai'));
    }

    public function test_city_delivery_is_enforced_in_quotes_and_saved_orders(): void
    {
        $this->configure(['mode' => 'store_city'], ['region' => 'IL']);
        $this->addToCart($this->sellable());
        $destination = ['country' => 'US', 'region' => 'IL', 'city' => ' Springfield ', 'shipping_method' => 'standard'];
        $this->get(route('checkout.show', $destination))->assertInertia(fn (Assert $page) => $page
            ->where('summary.shipping.code', 'standard'));
        $this->get(route('checkout.show', [...$destination, 'city' => 'Chicago']))->assertInertia(fn (Assert $page) => $page
            ->where('summary.shipping', null)
            ->has('summary.shipping_options', 0));
        $this->post(route('checkout.store'), $this->checkoutPayload(['shipping' => ['city' => 'Chicago']]))->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_invalid_locations_empty_country_lists_and_duplicates_are_rejected(): void
    {
        $this->actingAs($this->userWithRole('administrator'));
        foreach ([
            [$this->settings(['mode' => 'store_country'], ['country' => null]), 'profile.country'],
            [$this->settings(['mode' => 'store_region'], ['region' => '']), 'profile.region'],
            [$this->settings(['mode' => 'store_city'], ['city' => '']), 'profile.city'],
            [$this->settings(['mode' => 'selected_countries', 'countries' => []]), 'delivery.countries'],
            [$this->settings(['mode' => 'selected_countries', 'countries' => ['US', 'US']]), 'delivery.countries.0'],
            [$this->settings(['mode' => 'selected_countries', 'countries' => ['XX']]), 'delivery.countries.0'],
        ] as [$payload, $error]) {
            $this->put(route('admin.settings.store.update'), $payload)->assertSessionHasErrors($error);
        }
        $this->assertSame('shipping_methods', app(DeliveryArea::class)->toArray()['mode']);
    }

    public function test_duplicate_shipping_countries_and_region_aliases_are_rejected(): void
    {
        $this->actingAs($this->userWithRole('store-manager'));
        $payload = ['name' => 'Local', 'rate' => '5', 'countries' => ['AE', 'AE']];
        $this->post(route('admin.shipping-methods.store'), $payload)->assertSessionHasErrors('countries.0');
        $payload['countries'] = ['AE'];
        $payload['regional_rates'] = [
            ['country_code' => 'AE', 'region' => 'Dubai', 'rate' => '5'],
            ['country_code' => 'AE', 'region' => 'AE-DU', 'rate' => '10'],
        ];
        $this->post(route('admin.shipping-methods.store'), $payload)->assertSessionHasErrors('regional_rates.1.region');
    }

    public function test_shipping_page_reports_identical_methods_and_keeps_different_services(): void
    {
        $method = ShippingMethod::query()->firstOrFail();
        $copy = $method->replicate();
        $copy->code = 'standard_copy';
        $copy->name = 'Duplicate standard';
        $copy->save();
        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.shipping-methods.index'))
            ->assertInertia(fn (Assert $page) => $page->has('locationWarnings', 1));
        $copy->update(['rate' => '15']);
        $this->get(route('admin.shipping-methods.index'))->assertInertia(fn (Assert $page) => $page->has('locationWarnings', 0));
    }

    public function test_regions_only_coverage_does_not_offer_countries_without_a_regional_rate(): void
    {
        ShippingMethod::query()->update(['countries' => ['US', 'PH'], 'regions_only' => true, 'regional_rates' => [
            ['country_code' => 'US', 'region' => 'IL', 'rate' => '5', 'free_over' => null],
        ]]);
        $this->assertSame(['US'], app(ShippingCoverage::class)->codes('USD'));
    }

    public function test_digital_orders_are_not_restricted_by_delivery_country(): void
    {
        $this->configure(['mode' => 'store_country']);
        $this->addToCart($this->sellable('9', null, ['track_inventory' => false], ['is_digital' => true]));
        $this->get(route('checkout.show'))->assertInertia(fn (Assert $page) => $page
            ->where('soleCountry', null)
            ->where('deliveryRestriction', null)
            ->where('countries', fn ($countries) => count($countries) > 1));
        $this->post(route('checkout.store'), $this->checkoutPayload(['shipping' => ['country_code' => 'PH', 'region' => ''], 'shipping_method' => 'digital']))->assertSessionHasNoErrors();
        $this->assertSame('9.0000', Order::query()->firstOrFail()->grand_total);
    }
}

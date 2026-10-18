<?php

namespace Tests\Feature\Storefront;

use App\Core\Settings\SettingsRepository;
use App\Core\Themes\PageSettingFields;
use App\Domains\Customers\CustomerProfiles;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

/**
 * The free-shipping bar picks the same regional rule as the shipping rates
 * (TableShippingRateProvider), so it never promises free shipping that
 * checkout then charges for. Commit with the regional rates.
 */
class FreeShippingRegionalTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout(); // Standard: 5.00, free over 100.00
        config(['inertia.ssr.enabled' => false]);
        app(SettingsRepository::class)->set(PageSettingFields::STORE_KEY, ['cart.free_shipping_bar' => true]);
    }

    private function customerIn(string $country, string $region): void
    {
        $user = User::factory()->create();
        $profiles = app(CustomerProfiles::class);
        $profiles->saveAddress($profiles->forUser($user), ['first_name' => 'Ana', 'last_name' => 'Cruz', 'line1' => '1 Main St', 'city' => 'Town', 'region' => $region, 'postal_code' => '99501', 'country_code' => $country, 'is_default_shipping' => true]);
        $this->actingAs($user);
    }

    public function test_a_regional_rule_replaces_the_method_threshold()
    {
        ShippingMethod::query()->update(['regional_rates' => [['country_code' => 'US', 'region' => 'AK', 'rate' => '20.00', 'free_over' => '150.00']]]);
        $this->customerIn('US', 'AK');
        $this->addToCart($this->sellable('120.00', 5));

        // Over Standard's 100.00, but Alaska ships free only over 150.00.
        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
            ->where('freeShipping.threshold.amount', '150.00')
            ->where('freeShipping.remaining.amount', '30.00'));
    }

    public function test_a_rule_for_another_region_does_not_apply()
    {
        ShippingMethod::query()->update(['regional_rates' => [['country_code' => 'US', 'region' => 'AK', 'rate' => '20.00', 'free_over' => '150.00']]]);
        $this->customerIn('US', 'CA');
        $this->addToCart($this->sellable('30.00', 5));

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page->where('freeShipping.threshold.amount', '100.00'));
    }

    public function test_regions_only_methods_count_only_in_their_regions()
    {
        $local = new ShippingMethod(['name' => 'Local', 'rate' => '3.00', 'currency' => 'USD', 'free_over' => '20.00', 'is_active' => true, 'regions_only' => true,
            'regional_rates' => [['country_code' => 'US', 'region' => 'NY', 'rate' => '3.00', 'free_over' => null]]]);
        $local->code = 'local';
        $local->save();
        $this->customerIn('US', 'CA');
        $this->addToCart($this->sellable('10.00', 5));

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page->where('freeShipping.threshold.amount', '100.00'));
    }
}

<?php

namespace Tests\Feature\Shipping;

use App\Core\Settings\SettingsRepository;
use App\Domains\Pricing\Money;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Domains\Shipping\ShippingRequest;
use App\Domains\Shipping\TableShippingRateProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionalShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->withoutVite();
        app(SettingsRepository::class)->set('store.default_currency', 'AED');
    }

    private function payload(): array
    {
        return ['name' => 'Emirate delivery', 'rate' => '30', 'free_over' => '500', 'countries' => ['AE'], 'is_active' => true, 'delivery_estimate' => '1-2 days', 'regional_rates' => [
            ['country_code' => 'AE', 'region' => 'Dubai', 'rate' => '10', 'free_over' => '100'], ['country_code' => 'AE', 'region' => 'Abu Dhabi', 'rate' => '20', 'free_over' => null], ['country_code' => 'AE', 'region' => 'Sharjah', 'rate' => '15', 'free_over' => null]]];
    }

    private function quote(?string $region, string $total = '50', string $country = 'AE'): array
    {
        return app(TableShippingRateProvider::class)->rates(new ShippingRequest('AED', $country, $region, null, Money::of($total, 'AED'), '0', 1));
    }

    public function test_destination_rates_aliases_fallback_and_free_shipping(): void
    {
        $this->actingAs($this->userWithRole('store-manager'))->post(route('admin.shipping-methods.store'), $this->payload())->assertSessionHasNoErrors();
        foreach (['Dubai' => '10.00', ' dubai ' => '10.00', 'AE-DU' => '10.00', 'Abu Dhabi' => '20.00', 'abudhabie' => '20.00', 'Sharjah' => '15.00', 'Ajman' => '30.00'] as $region => $amount) {
            $this->assertSame($amount, $this->quote($region)[0]->amount->toDisplayDecimal());
        }
        $this->assertSame('0.00', $this->quote('Dubai', '100')[0]->amount->toDisplayDecimal());
        $this->assertSame('0.00', $this->quote('Sharjah', '500')[0]->amount->toDisplayDecimal());
        $this->assertSame('30.00', $this->quote(null)[0]->amount->toDisplayDecimal());
        $this->assertSame('1-2 days', $this->quote('Dubai')[0]->description);
        $this->assertSame([], $this->quote('Dubai', '50', 'US'));
        $method = ShippingMethod::firstOrFail();
        $method->update(['regions_only' => true]);
        $this->assertSame([], $this->quote('Ajman'));
        $this->assertSame([], $this->quote(null));
        $this->assertCount(1, $this->quote('Dubai'));
        $method->update(['is_active' => false]);
        $this->assertSame([], $this->quote('Dubai'));
    }

    public function test_invalid_duplicate_and_empty_restrictions_are_rejected(): void
    {
        $this->actingAs($this->userWithRole('store-manager'));
        $data = $this->payload();
        $data['regional_rates'][1]['region'] = 'AE-DU';
        $this->post(route('admin.shipping-methods.store'), $data)->assertSessionHasErrors('regional_rates.1.region');
        $data = $this->payload();
        $data['regional_rates'][0]['rate'] = '-1';
        $this->post(route('admin.shipping-methods.store'), $data)->assertSessionHasErrors('regional_rates.0.rate');
        $data = $this->payload();
        $data['regions_only'] = true;
        $data['regional_rates'] = [];
        $this->post(route('admin.shipping-methods.store'), $data)->assertSessionHasErrors('regional_rates');
        $data = $this->payload();
        $data['countries'] = ['US'];
        $this->post(route('admin.shipping-methods.store'), $data)->assertSessionHasErrors('regional_rates.0.country_code');
    }
}

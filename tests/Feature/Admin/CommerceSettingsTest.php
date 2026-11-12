<?php

namespace Tests\Feature\Admin;

use App\Domains\Customers\Models\Customer;
use App\Domains\Discounts\Models\Coupon;
use App\Domains\Payments\PaymentMethods;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Domains\Tax\Models\TaxRateRow;
use App\Domains\Tax\TaxSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommerceSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    public function test_coupon_crud_and_validation()
    {
        $manager = $this->userWithRole('store-manager');

        $this->actingAs($manager)->post(route('admin.coupons.store'), [
            'code' => 'summer-10', 'type' => 'percentage', 'value' => '150', 'applies_to' => 'all',
        ])->assertSessionHasErrors('value');

        $this->actingAs($manager)->post(route('admin.coupons.store'), [
            'code' => 'summer-10', 'type' => 'percentage', 'value' => '10', 'applies_to' => 'all', 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $coupon = Coupon::query()->where('code', 'SUMMER-10')->firstOrFail();
        $this->assertNull($coupon->currency);

        $this->actingAs($manager)->put(route('admin.coupons.update', $coupon), [
            'code' => 'SUMMER-10', 'type' => 'fixed', 'value' => '5', 'applies_to' => 'all',
        ])->assertSessionHasNoErrors();
        $this->assertSame('USD', $coupon->fresh()?->currency);

        $this->actingAs($manager)->delete(route('admin.coupons.destroy', $coupon))->assertRedirect();
        $this->assertSoftDeleted($coupon);

        $this->actingAs($this->userWithRole('analyst'))->get(route('admin.coupons.index'))->assertOk();
        $this->actingAs($this->userWithRole('analyst'))->post(route('admin.coupons.store'), ['code' => 'X'])->assertForbidden();
    }

    public function test_shipping_methods_crud()
    {
        $manager = $this->userWithRole('store-manager');

        $this->actingAs($manager)->post(route('admin.shipping-methods.store'), [
            'name' => 'Express', 'rate' => '12.5', 'countries' => ['US', 'XX'], 'is_active' => true,
        ])->assertSessionHasErrors('countries.1');

        $this->actingAs($manager)->post(route('admin.shipping-methods.store'), [
            'name' => 'Express', 'rate' => '12.5', 'countries' => ['US'], 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $method = ShippingMethod::query()->firstOrFail();
        $this->assertSame('express', $method->code);
        $this->assertSame(['US'], $method->countries);

        $this->actingAs($manager)->put(route('admin.shipping-methods.update', $method), [
            'name' => 'Express 2', 'rate' => '10', 'code' => 'changed',
        ])->assertSessionHasNoErrors();
        $this->assertSame('express', $method->fresh()?->code);

        $this->actingAs($this->userWithRole('order-manager'))->get(route('admin.shipping-methods.index'))->assertForbidden();
    }

    public function test_tax_rates_and_settings()
    {
        $admin = $this->userWithRole('administrator');

        $this->actingAs($admin)->post(route('admin.tax-rates.store'), ['name' => 'VAT', 'country_code' => 'PH', 'rate' => '12'])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('admin.tax-rates.store'), ['name' => 'Bad', 'country_code' => 'PH', 'rate' => '120'])->assertSessionHasErrors('rate');
        $this->assertSame(1, TaxRateRow::query()->count());

        $this->actingAs($admin)->put(route('admin.tax-rates.settings'), ['prices_include_tax' => '1'])->assertSessionHasNoErrors();
        $this->assertTrue(app(TaxSettings::class)->pricesIncludeTax());
        $this->assertFalse(app(TaxSettings::class)->taxShipping());

        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.tax-rates.index'))->assertForbidden();
    }

    public function test_payment_settings()
    {
        $admin = $this->userWithRole('administrator');
        $payload = [
            'guest_checkout' => '1',
            'methods' => [
                'bank_transfer' => ['enabled' => '1', 'label' => 'Bank deposit', 'instructions' => 'Pay to account 123.'],
                'cash_on_delivery' => ['label' => 'COD', 'instructions' => 'Pay the courier.'],
            ],
        ];

        $this->actingAs($admin)->put(route('admin.payments.settings.update'), $payload)->assertSessionHasNoErrors();

        $enabled = app(PaymentMethods::class)->enabled();
        $this->assertSame(['bank_transfer'], array_keys($enabled));
        $this->assertSame('Bank deposit', $enabled['bank_transfer']->label());

        // Errors name the field in plain words (shown inside the method's tab).
        $payload['methods']['cash_on_delivery']['label'] = '';
        $this->put(route('admin.payments.settings.update'), $payload)
            ->assertSessionHasErrors(['methods.cash_on_delivery.label' => 'The name shown to customers field is required.']);

        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.payments.settings'))->assertForbidden();
    }

    public function test_customer_admin_pages()
    {
        $customer = Customer::query()->create(['email' => 'guest@example.com', 'first_name' => 'Gus']);

        $this->actingAs($this->userWithRole('support-agent'))->get(route('admin.customers.index', ['search' => 'gus']))
            ->assertInertia(fn ($page) => $page->has('customers.data', 1));

        $this->actingAs($this->userWithRole('support-agent'))
            ->put(route('admin.customers.update', $customer), ['first_name' => 'Gustavo', 'email' => 'hijack@example.com'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Gustavo', $customer->fresh()?->first_name);
        $this->assertSame('guest@example.com', $customer->fresh()?->email);

        $this->actingAs($this->userWithRole('analyst'))->put(route('admin.customers.update', $customer), ['first_name' => 'X'])->assertForbidden();
    }
}

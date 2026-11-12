<?php

namespace Tests\Support;

use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Discounts\Models\Coupon;
use App\Domains\Shipping\Models\ShippingMethod;
use App\Domains\Tax\Models\TaxRateRow;
use App\Domains\Tax\TaxSettings;
use Illuminate\Support\Str;

/**
 * Builds a sellable store: payment methods, shipping, tax and products.
 */
trait CommerceFixtures
{
    protected function enableCheckout(bool $cod = true): void
    {
        app(SettingsRepository::class)->setMany([
            'payments.bank_transfer.enabled' => true,
            'payments.cash_on_delivery.enabled' => $cod,
        ]);

        $method = new ShippingMethod(['name' => 'Standard', 'rate' => '5.00', 'currency' => 'USD', 'free_over' => '100.00', 'countries' => ['US', 'PH'], 'is_active' => true]);
        $method->code = 'standard';
        $method->save();
    }

    protected function taxRate(string $country = 'US', string $rate = '10', ?string $region = null, bool $inclusive = false, bool $shipping = false): void
    {
        TaxRateRow::query()->create(['name' => 'Sales tax', 'country_code' => $country, 'region' => $region, 'rate' => $rate, 'is_active' => true]);
        app(TaxSettings::class)->update($inclusive, $shipping);
    }

    protected function sellable(string $price = '20.00', ?int $stock = 10, array $variant = [], array $product = []): ProductVariant
    {
        return Product::factory()->withVariant($price, $stock, variantAttributes: $variant)->create($product)->variants()->firstOrFail();
    }

    protected function coupon(array $attributes = []): Coupon
    {
        return Coupon::query()->forceCreate(array_merge([
            'code' => 'SAVE'.Str::upper(Str::random(4)),
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => '10',
            'applies_to' => Coupon::APPLIES_ALL,
            'is_active' => true,
        ], $attributes));
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkoutPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'email' => 'buyer@example.com',
            'phone' => '555-0100',
            'shipping' => [
                'first_name' => 'Ana',
                'last_name' => 'Cruz',
                'line1' => '1 Main St',
                'city' => 'Springfield',
                'region' => 'IL',
                'postal_code' => '62701',
                'country_code' => 'US',
            ],
            'billing_same' => true,
            'shipping_method' => 'standard',
            'payment_method' => 'bank_transfer',
            'checkout_token' => (string) Str::uuid(),
        ], $overrides);
    }

    protected function addToCart(ProductVariant $variant, int $quantity = 1): void
    {
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => $quantity])->assertSessionHasNoErrors();
    }
}

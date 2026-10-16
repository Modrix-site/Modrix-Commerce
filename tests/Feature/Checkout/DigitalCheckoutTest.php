<?php

namespace Tests\Feature\Checkout;

use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Checkout\Pricing\CartPricer;
use App\Domains\Checkout\Pricing\PricingContext;
use App\Domains\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class DigitalCheckoutTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        config(['inertia.ssr.enabled' => false]);
    }

    private function digital(string $price = '9.00'): ProductVariant
    {
        return $this->sellable($price, null, ['track_inventory' => false], ['is_digital' => true]);
    }

    public function test_digital_only_cart_checks_out_without_shipping_method()
    {
        $this->addToCart($this->digital());

        $this->get(route('checkout.show', ['country' => 'US']))->assertInertia(fn (Assert $page) => $page
            ->where('summary.requires_shipping', false)
            ->where('summary.shipping.code', 'digital'));

        $payload = $this->checkoutPayload();
        unset($payload['shipping_method']);
        $this->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();

        $order = Order::query()->latest('id')->firstOrFail();
        $this->assertSame('0.0000', $order->shipping_total);
        $this->assertSame('digital', $order->shipping_method_code);
        $this->assertSame('No shipping needed', $order->shipping_method_name);
        $this->assertSame('9.0000', $order->grand_total);
    }

    public function test_mixed_cart_still_requires_shipping_method()
    {
        $this->addToCart($this->digital());
        $this->addToCart($this->sellable('20.00', 5));

        $payload = $this->checkoutPayload();
        unset($payload['shipping_method']);
        $this->post(route('checkout.store'), $payload)->assertSessionHasErrors('shipping_method');

        // Choosing "digital" for a cart with physical items is not possible either.
        $this->post(route('checkout.store'), $this->checkoutPayload(['shipping_method' => 'digital']))->assertSessionHasErrors('shipping_method');
    }

    public function test_removing_the_last_physical_item_drops_shipping()
    {
        $this->addToCart($this->digital());
        $physical = $this->sellable('20.00', 5);
        $this->addToCart($physical);
        $cart = Cart::query()->latest('id')->firstOrFail();
        $context = new PricingContext(countryCode: 'US', shippingMethod: 'standard');

        $before = app(CartPricer::class)->price($cart, $context);
        $this->assertTrue($before->requiresShipping());
        $this->assertSame('5.0000', $before->shippingTotal->toDecimal());

        $cart->items()->where('variant_id', $physical->id)->delete();
        $after = app(CartPricer::class)->price($cart->fresh(), $context);
        $this->assertFalse($after->requiresShipping());
        $this->assertSame('0.0000', $after->shippingTotal->toDecimal());
        $this->assertSame('digital', $after->shipping['code'] ?? null);
    }
}

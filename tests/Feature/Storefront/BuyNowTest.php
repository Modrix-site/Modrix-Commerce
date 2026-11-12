<?php

namespace Tests\Feature\Storefront;

use App\Domains\Cart\Models\CartItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class BuyNowTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        config(['inertia.ssr.enabled' => false]);
    }

    public function test_buy_now_adds_the_item_and_goes_to_checkout()
    {
        $variant = $this->sellable('20.00', 5);

        $this->from(route('storefront.product', $variant->product->slug))
            ->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 2, 'buy_now' => '1'])
            ->assertRedirect(route('checkout.show'));

        $this->assertSame(2, CartItem::query()->where('variant_id', $variant->id)->value('quantity'));
    }

    public function test_add_to_cart_still_returns_to_the_page()
    {
        $variant = $this->sellable('20.00', 5);
        $page = route('storefront.product', $variant->product->slug);

        $this->from($page)->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1])
            ->assertRedirect($page);
    }

    public function test_product_page_receives_the_buy_button_override()
    {
        $product = $this->sellable()->product;

        $this->get(route('storefront.product', $product->slug))->assertInertia(fn (Assert $page) => $page
            ->has('product.show_buy_button'));
    }
}

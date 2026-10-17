<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class StorefrontPlacesTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        config(['inertia.ssr.enabled' => false]);
    }

    private function block(string $place, ?callable $props = null): void
    {
        app(StorefrontSlots::class)->block('acme/test', str_replace('.', '_', $place), 'Test', 'TestBlock', [$place], $place, $props ?? fn (array $context) => ['place' => $context['place']]);
    }

    private static function slotCount(mixed $slots, string $place): int
    {
        return count(collect($slots)->toArray()[$place] ?? []);
    }

    public function test_product_page_sends_its_places()
    {
        foreach (['product.after_price', 'product.after_add_to_cart', 'product.after_description'] as $place) {
            $this->block($place);
        }
        $product = Product::factory()->withVariant('10.00')->create();

        $this->get(route('storefront.product', $product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('placeSlots', fn ($slots) => self::slotCount($slots, 'product.after_add_to_cart') === 1
                && self::slotCount($slots, 'product.after_description') === 1
                && collect($slots)->toArray()['product.after_price'][0]['props']['place'] === 'product.after_price'));
    }

    public function test_card_blocks_are_shared_once()
    {
        $calls = 0;
        $this->block('product.card', function () use (&$calls) {
            $calls++;

            return ['badge' => 'New'];
        });
        $this->block('header.actions');
        Product::factory()->count(6)->withVariant('10.00')->create();

        $this->get(route('storefront.search'))->assertInertia(fn (Assert $page) => $page
            ->has('products.data', 6)
            ->where('storefront.placeSlots', fn ($slots) => collect($slots)->toArray()['product.card'][0]['props']['badge'] === 'New'
                && self::slotCount($slots, 'header.actions') === 1)
            ->where('placeSlots', fn ($slots) => self::slotCount($slots, 'catalog.before_products') === 0));
        $this->assertSame(1, $calls);
    }

    public function test_cart_and_checkout_send_their_places()
    {
        $this->block('cart.after_lines');
        $this->block('checkout.before_place_order');
        $this->addToCart($this->sellable('20.00', 5));

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page->where('placeSlots', fn ($slots) => self::slotCount($slots, 'cart.after_lines') === 1));
        $this->get(route('checkout.show'))->assertInertia(fn (Assert $page) => $page->where('placeSlots', fn ($slots) => self::slotCount($slots, 'checkout.before_place_order') === 1));
    }
}

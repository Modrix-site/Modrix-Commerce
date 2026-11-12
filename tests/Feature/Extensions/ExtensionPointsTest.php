<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\DashboardWidgets;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Contracts\SearchProvider;
use App\Domains\Catalog\Models\Product;
use App\Domains\Checkout\CheckoutData;
use App\Domains\Checkout\CheckoutValidators;
use App\Domains\Checkout\Contracts\CheckoutValidator;
use App\Domains\Checkout\Pricing\PricedCart;
use App\Domains\Orders\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use InvalidArgumentException;
use RuntimeException;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class ExtensionPointsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        Mail::fake();
    }

    public function test_a_checkout_validator_can_refuse_an_order_before_stock_is_taken()
    {
        $this->enableCheckout();
        app(CheckoutValidators::class)->register(new class implements CheckoutValidator
        {
            public function validate(PricedCart $cart, CheckoutData $data): ?string
            {
                return $data->shippingAddress['country_code'] === 'US' ? 'We cannot ship this product to the United States.' : null;
            }
        });
        $variant = $this->sellable('10.00', 5);
        $this->addToCart($variant);

        $this->post(route('checkout.store'), $this->checkoutPayload())
            ->assertSessionHasErrors(['cart' => 'We cannot ship this product to the United States.']);

        $this->assertSame(0, Order::query()->count());
        $this->assertSame(5, $variant->fresh()->onHand());
    }

    public function test_a_failing_checkout_validator_blocks_checkout()
    {
        $this->enableCheckout();
        app(CheckoutValidators::class)->register(new class implements CheckoutValidator
        {
            public function validate(PricedCart $cart, CheckoutData $data): ?string
            {
                throw new RuntimeException('age verification service down');
            }
        });
        $this->addToCart($this->sellable());

        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::query()->count());
    }

    public function test_storefront_search_can_be_replaced_without_exposing_hidden_products()
    {
        $visible = $this->sellable(product: ['name' => 'Blue Mug']);
        $this->sellable(product: ['name' => 'Secret Draft', 'status' => 'draft']);

        // A provider that matches everything must still not reveal drafts.
        $this->app->bind(SearchProvider::class, fn () => new class implements SearchProvider
        {
            public function apply(Builder $products, string $term): Builder
            {
                return $products->orWhereRaw('1 = 1');
            }
        });

        $this->get(route('storefront.search', ['q' => 'anything']))
            ->assertInertia(fn (Assert $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.name', $visible->product->name));
    }

    public function test_storefront_slots_render_module_components_and_skip_failing_ones()
    {
        $product = $this->sellable(product: ['name' => 'Tea'])->product;
        $slots = app(StorefrontSlots::class);
        $slots->register('product.after_details', 'acme/hello', 'Greeting', fn (array $context) => ['name' => $context['product']->name]);
        $slots->register('product.after_details', 'acme/broken', 'Broken', fn () => throw new RuntimeException('boom'));
        $slots->register('product.after_details', 'acme/quiet', 'Quiet', fn () => null);

        $this->get(route('storefront.product', $product->slug))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('slots', 1)
                ->where('slots.0.component', 'acme/hello/Greeting')
                ->where('slots.0.props.name', 'Tea'));
    }

    public function test_slot_registration_is_validated()
    {
        $this->expectException(InvalidArgumentException::class);
        app(StorefrontSlots::class)->register('checkout.payment_form', 'acme/hello', 'Card', fn () => []);
    }

    public function test_dashboard_widgets_are_shown_only_to_permitted_staff()
    {
        app(DashboardWidgets::class)->register('sales', 'acme/hello', 'Sales today', 'SalesWidget', 'reports.view', fn () => ['total' => '10.00']);

        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('widgets', 1)->where('widgets.0.component', 'acme/hello/SalesWidget'));

        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('widgets', 0));
    }

    public function test_products_are_still_found_by_the_default_search()
    {
        $this->sellable(product: ['name' => 'Ceramic Teapot']);
        $this->assertSame(1, Product::query()->count());

        $this->get(route('storefront.search', ['q' => 'teapot']))
            ->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
    }
}

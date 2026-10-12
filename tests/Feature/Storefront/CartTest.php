<?php

namespace Tests\Feature\Storefront;

use App\Domains\Cart\CartManager;
use App\Domains\Cart\Models\Cart;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
    }

    private function variant(string $price = '10.00', ?int $stock = 5, array $attributes = []): ProductVariant
    {
        return Product::factory()->withVariant($price, $stock, variantAttributes: $attributes)->create()->variants()->firstOrFail();
    }

    public function test_guest_can_add_items_and_quantities_merge()
    {
        $variant = $this->variant('12.50');

        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 2])->assertSessionHasNoErrors();
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1])->assertSessionHasNoErrors();

        $token = session(CartManager::SESSION_KEY);
        $this->assertIsString($token);
        $cart = Cart::query()->where('token', $token)->firstOrFail();
        $this->assertNull($cart->user_id);
        $this->assertSame(3, $cart->items()->firstOrFail()->quantity);

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
            ->component('storefront/cart')
            ->where('cart.subtotal.amount', '37.50')
            ->where('cart.can_checkout', true)
            ->where('storefront.cartCount', 3));
    }

    public function test_client_supplied_prices_are_ignored()
    {
        $variant = $this->variant('40.00');

        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1, 'price' => '0.01', 'unit_price' => '0.01']);

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page->where('cart.subtotal.amount', '40.00'));
    }

    public function test_stock_limits_and_unavailable_products_are_enforced()
    {
        $limited = $this->variant(stock: 2);
        $out = $this->variant(stock: 0);
        $draft = Product::factory()->draft()->withVariant()->create()->variants()->firstOrFail();
        $inactive = $this->variant(attributes: ['is_active' => false]);
        $backorder = $this->variant(stock: 0, attributes: ['allow_backorder' => true]);

        $this->post(route('cart.items.store'), ['variant_id' => $limited->id, 'quantity' => 3])->assertSessionHasErrors('quantity');
        $this->post(route('cart.items.store'), ['variant_id' => $out->id, 'quantity' => 1])->assertSessionHasErrors('quantity');
        $this->post(route('cart.items.store'), ['variant_id' => $draft->id, 'quantity' => 1])->assertNotFound();
        $this->post(route('cart.items.store'), ['variant_id' => $inactive->id, 'quantity' => 1])->assertSessionHasErrors('quantity');
        $this->post(route('cart.items.store'), ['variant_id' => $backorder->id, 'quantity' => 4])->assertSessionHasNoErrors();
    }

    public function test_cannot_modify_items_of_another_cart()
    {
        $variant = $this->variant();
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $otherItem = CartItem::query()->firstOrFail();

        // A different visitor (fresh session) cannot touch it.
        $this->flushSession();
        $this->post(route('cart.items.store'), ['variant_id' => $this->variant()->id, 'quantity' => 1]);

        $this->patch(route('cart.items.update', $otherItem), ['quantity' => 5])->assertNotFound();
        $this->delete(route('cart.items.destroy', $otherItem))->assertNotFound();
        $this->assertSame(1, $otherItem->fresh()?->quantity);
    }

    public function test_update_to_zero_removes_the_line()
    {
        $variant = $this->variant();
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 2]);
        $item = CartItem::query()->firstOrFail();

        $this->patch(route('cart.items.update', $item), ['quantity' => 0])->assertRedirect(route('cart.show'));
        $this->assertModelMissing($item);
    }

    public function test_price_changes_are_announced_once()
    {
        $variant = $this->variant('10.00');
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 1]);
        $variant->prices()->update(['amount' => '12.00']);

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
            ->where('cart.subtotal.amount', '12.00')
            ->where('cart.lines.0.notices', fn ($notices) => str_contains((string) collect($notices)->first(), 'price changed')));

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page->where('cart.lines.0.notices', []));
    }

    public function test_stock_drop_after_adding_blocks_checkout()
    {
        $variant = $this->variant(stock: 3);
        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 3]);
        $variant->stocks()->update(['on_hand' => 1]);

        $this->get(route('cart.show'))->assertInertia(fn (Assert $page) => $page
            ->where('cart.can_checkout', false)
            ->where('cart.lines.0.purchasable', false));
    }

    public function test_guest_cart_merges_into_user_cart_on_login_capped_by_stock()
    {
        $variant = $this->variant(stock: 4);
        $user = User::factory()->create();

        $existing = app(CartManager::class)->forUser($user);
        app(CartManager::class)->add($existing, $variant, 3);

        $this->post(route('cart.items.store'), ['variant_id' => $variant->id, 'quantity' => 2]);
        $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, Cart::query()->count());
        $this->assertSame(4, $existing->items()->firstOrFail()->quantity);
    }
}

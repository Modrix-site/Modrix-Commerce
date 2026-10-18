<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Domains\Cart\Models\Cart;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Inventory\InventoryService;
use App\Domains\Inventory\MovementReason;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Enums\PaymentStatus;
use App\Domains\Orders\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * The bundled "Frequently Bought Together" module, exercised from a copy of
 * modules/: suggestions from order history, the add-all endpoint, and the
 * admin page.
 */
class FrequentlyBoughtTogetherTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private ModuleManager $modules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules'), $this->modulesPath);
        $this->modules = app(ModuleManager::class);
        $this->modules->install('ecomodular/frequently-bought-together');
        $this->modules->enable('ecomodular/frequently-bought-together', true);
    }

    private function state(string $name): ModuleState
    {
        return Extension::query()->where('name', $name)->firstOrFail()->state;
    }

    /**
     * Records a paid order containing the given products (one line each), so
     * the recommender has real co-purchase history to learn from.
     *
     * @param  list<Product>  $products
     */
    private function paidOrder(array $products): void
    {
        $order = Order::query()->forceCreate([
            'number' => 'FBT-'.str_pad((string) (Order::query()->count() + 1), 6, '0', STR_PAD_LEFT),
            'email' => 'buyer@example.com',
            'currency' => 'USD',
            'status' => OrderStatus::Confirmed,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'subtotal' => '10.0000',
            'grand_total' => '10.0000',
            'paid_total' => '10.0000',
            'payment_method_code' => 'bank_transfer',
            'payment_method_name' => 'Bank transfer',
            'placed_at' => now(),
            'paid_at' => now(),
        ]);

        foreach ($products as $product) {
            $variant = $product->variants()->firstOrFail();
            DB::table('order_items')->insert([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'variant_id' => $variant->id,
                'sku' => $variant->sku,
                'product_name' => $product->name,
                'quantity' => 1,
                'unit_price' => '10.0000',
                'subtotal' => '10.0000',
                'discount_total' => '0.0000',
                'tax_rate' => '0.0000',
                'tax_total' => '0.0000',
                'total' => '10.0000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_module_is_enabled_and_registers_its_routes()
    {
        $this->assertSame(ModuleState::Enabled, $this->state('ecomodular/frequently-bought-together'));
        $this->assertTrue(app('router')->has('admin.ecomodular.frequently-bought-together.index'));
        $this->assertTrue(app('router')->has('ecomodular.frequently-bought-together.add'));
    }

    public function test_slot_suggests_products_bought_together_from_order_history()
    {
        $viewed = $this->sellable('10.00')->product;
        $often = $this->sellable('12.00')->product;
        $sometimes = $this->sellable('14.00')->product;

        // $often was bought with $viewed twice; $sometimes once.
        $this->paidOrder([$viewed, $often]);
        $this->paidOrder([$viewed, $often]);
        $this->paidOrder([$viewed, $sometimes]);

        $this->get(route('storefront.product', $viewed->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('slots.0.component', 'ecomodular/frequently-bought-together/BundleWidget')
                ->where('slots.0.props.current.id', $viewed->id)
                ->has('slots.0.props.items', 2)
                // Most frequently co-purchased first.
                ->where('slots.0.props.items.0.id', $often->id)
                ->where('slots.0.props.items.1.id', $sometimes->id));
    }

    public function test_a_product_below_the_minimum_co_purchase_count_is_not_suggested()
    {
        $viewed = $this->sellable('10.00')->product;
        $companion = $this->sellable('12.00')->product;

        $this->modules->configure('ecomodular/frequently-bought-together', [
            'min_orders' => '2',
            'fallback_related' => '0',
        ], $this->owner());

        $this->paidOrder([$viewed, $companion]); // only once

        // Not enough proof and no fallback: nothing is shown.
        $this->get(route('storefront.product', $viewed->slug))
            ->assertInertia(fn (Assert $page) => $page->has('slots', 0));
    }

    public function test_fallback_suggests_related_products_from_the_same_category()
    {
        $category = Category::factory()->create();
        $viewed = $this->sellable('10.00')->product;
        $relative = $this->sellable('12.00')->product;
        $viewed->categories()->attach($category);
        $relative->categories()->attach($category);

        // No order history at all; the fallback fills the bundle.
        $this->get(route('storefront.product', $viewed->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('slots.0.component', 'ecomodular/frequently-bought-together/BundleWidget')
                ->where('slots.0.props.items.0.id', $relative->id));
    }

    public function test_disabled_module_shows_no_bundle()
    {
        $viewed = $this->sellable()->product;

        $this->modules->configure('ecomodular/frequently-bought-together', ['enabled' => '0'], $this->owner());

        $this->get(route('storefront.product', $viewed->slug))
            ->assertInertia(fn (Assert $page) => $page->has('slots', 0));
    }

    public function test_add_all_puts_every_chosen_product_in_the_cart()
    {
        $viewed = $this->sellable('10.00')->product;
        $a = $this->sellable('12.00');
        $b = $this->sellable('14.00');
        $this->paidOrder([$viewed, $a->product, $b->product]);

        $this->post(route('ecomodular.frequently-bought-together.add'), [
            'product_id' => $viewed->id,
            'variant_ids' => [$a->id, $b->id],
        ])->assertSessionHasNoErrors();

        $cart = Cart::query()->latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$a->id, $b->id],
            $cart->items()->pluck('variant_id')->map(fn ($id) => (int) $id)->all(),
        );
    }

    public function test_add_all_rejects_an_unavailable_variant()
    {
        $viewed = $this->sellable('10.00')->product;
        $soldOut = $this->sellable('12.00', 1);
        // Drain the stock.
        app(InventoryService::class)->adjust($soldOut, -1, MovementReason::Adjustment);

        $this->post(route('ecomodular.frequently-bought-together.add'), [
            'product_id' => $viewed->id,
            'variant_ids' => [$soldOut->id],
        ])->assertSessionHasErrors('bundle');
    }

    public function test_bundle_includes_the_selected_current_variant_and_rejects_a_foreign_variant(): void
    {
        $current = $this->sellable('10.00');
        $extra = $this->sellable('12.00');
        $url = route('ecomodular.frequently-bought-together.add');
        $this->post($url, [
            'product_id' => $current->product_id,
            'current_variant_id' => $extra->id,
            'variant_ids' => [$extra->id],
        ])->assertNotFound();
        $this->assertSame(0, Cart::query()->count());
        $this->post($url, [
            'product_id' => $current->product_id,
            'current_variant_id' => $current->id,
            'variant_ids' => [$extra->id],
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$current->id, $extra->id], Cart::query()->latest('id')->firstOrFail()->items()->pluck('variant_id')->all());
    }

    public function test_add_all_is_rejected_when_the_feature_is_switched_off()
    {
        $viewed = $this->sellable('10.00')->product;
        $variant = $this->sellable('12.00');

        $this->modules->configure('ecomodular/frequently-bought-together', ['enabled' => '0'], $this->owner());

        $this->post(route('ecomodular.frequently-bought-together.add'), [
            'product_id' => $viewed->id,
            'variant_ids' => [$variant->id],
        ])->assertNotFound();
    }

    public function test_admin_page_requires_the_bundles_permission_and_shows_a_preview()
    {
        $this->sellable('10.00');

        $this->actingAs($this->userWithRole('catalog-manager'))
            ->get(route('admin.ecomodular.frequently-bought-together.index'))
            ->assertForbidden();

        $this->actingAs($this->owner())
            ->get(route('admin.ecomodular.frequently-bought-together.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/ecomodular/frequently-bought-together/admin/index', false)
                ->where('state.enabled', true)
                ->has('state.max_products'));
    }
}

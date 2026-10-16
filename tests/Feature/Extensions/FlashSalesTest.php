<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\StorefrontSlots;
use App\Core\Settings\StoreSettings;
use App\Domains\Cart\Models\CartItem;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Pricing\SalePrices;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use EcoModular\FlashSales\Models\FlashSale;
use EcoModular\FlashSales\Models\FlashSaleProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * The bundled Flash Sales module, exercised from a copy of modules/.
 */
class FlashSalesTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const ROUTE = 'admin.ecomodular.flash-sales.';

    private User $marketer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        Mail::fake();
        $this->setUpModules();
        File::copyDirectory(base_path('modules'), $this->modulesPath);
        $modules = app(ModuleManager::class);
        $modules->install('ecomodular/flash-sales');
        $modules->enable('ecomodular/flash-sales', true);
        $this->marketer = $this->owner();
    }

    private function sale(array $attributes = []): FlashSale
    {
        return FlashSale::query()->create([
            'name' => 'Weekend Flash Sale',
            'discount_type' => 'percent',
            'discount_value' => '20',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addDay(),
            'is_active' => true,
            'show_banner' => false,
            ...$attributes,
        ]);
    }

    private function product(string $price = '50.00'): Product
    {
        return $this->sellable($price, 10)->product;
    }

    private function price(Product $product): ?string
    {
        app(SalePrices::class)->flush();

        return app(SalePrices::class)->forVariant($product->variants()->firstOrFail(), app(StoreSettings::class)->currency())['price']?->toDisplayDecimal();
    }

    public function test_sales_are_created_with_validation_and_listed()
    {
        $this->actingAs($this->marketer);
        $payload = ['name' => 'Summer', 'discount_type' => 'percent', 'discount_value' => '25', 'starts_at' => '2026-11-01T09:00', 'ends_at' => '2026-11-03T21:00', 'is_active' => '1', 'show_banner' => '1'];

        $this->post(route(self::ROUTE.'store'), $payload)->assertSessionHasNoErrors();
        $sale = FlashSale::query()->firstOrFail();
        $this->assertSame('25.0000', $sale->discount_value);
        $this->assertTrue($sale->show_banner);
        $this->assertDatabaseHas('audit_logs', ['action' => 'flash_sale.created']);

        $this->post(route(self::ROUTE.'store'), [...$payload, 'discount_value' => '95'])->assertSessionHasErrors('discount_value');
        $this->post(route(self::ROUTE.'store'), [...$payload, 'discount_type' => 'fixed', 'discount_value' => '0'])->assertSessionHasErrors('discount_value');
        $this->post(route(self::ROUTE.'store'), [...$payload, 'ends_at' => '2026-10-31T09:00'])->assertSessionHasErrors('ends_at');

        $this->get(route(self::ROUTE.'index'))->assertInertia(fn (Assert $page) => $page
            ->component('module/ecomodular/flash-sales/admin/index', false)
            ->where('sales.0.name', 'Summer')
            ->where('sales.0.state', 'scheduled'));
    }

    public function test_live_sales_reduce_prices_by_percentage_or_fixed_amount_with_overrides()
    {
        $a = $this->product('50.00');
        $b = $this->product('50.00');
        $c = $this->product('3.00');
        $sale = $this->sale();

        $this->actingAs($this->marketer)->post(route(self::ROUTE.'products.store', $sale), ['product_ids' => [$a->id, $b->id, $c->id]])->assertSessionHasNoErrors();
        $itemB = FlashSaleProduct::query()->where('product_id', $b->id)->firstOrFail();
        $this->put(route(self::ROUTE.'products.update', [$sale, $itemB]), ['discount_type' => 'fixed', 'discount_value' => '15'])->assertSessionHasNoErrors();
        $itemC = FlashSaleProduct::query()->where('product_id', $c->id)->firstOrFail();
        $this->put(route(self::ROUTE.'products.update', [$sale, $itemC]), ['discount_type' => 'fixed', 'discount_value' => '10'])->assertSessionHasNoErrors();

        $this->assertSame('40.00', $this->price($a)); // 20% off
        $this->assertSame('35.00', $this->price($b)); // own: 15 off
        $this->assertSame('0.00', $this->price($c));  // never below zero
    }

    public function test_scheduled_paused_or_ended_sales_do_not_discount()
    {
        $product = $this->product();
        foreach ([
            ['starts_at' => now()->addHour()],
            ['is_active' => false],
            ['starts_at' => now()->subDays(2), 'ends_at' => now()->subMinute()],
        ] as $attributes) {
            FlashSaleProduct::query()->create(['flash_sale_id' => $this->sale($attributes)->id, 'product_id' => $product->id]);
        }

        $this->assertNull(app(SalePrices::class)->forVariant($product->variants()->firstOrFail(), app(StoreSettings::class)->currency()));

        // When the sale starts, the discount applies.
        FlashSaleProduct::query()->create(['flash_sale_id' => $this->sale(['discount_value' => '10'])->id, 'product_id' => $product->id]);
        $this->assertSame('45.00', $this->price($product));
    }

    public function test_the_deepest_of_overlapping_sales_wins()
    {
        $product = $this->product('100.00');
        FlashSaleProduct::query()->create(['flash_sale_id' => $this->sale(['discount_value' => '10'])->id, 'product_id' => $product->id]);
        FlashSaleProduct::query()->create(['flash_sale_id' => $this->sale(['discount_type' => 'fixed', 'discount_value' => '25'])->id, 'product_id' => $product->id]);

        $this->assertSame('75.00', $this->price($product));
    }

    public function test_the_product_editor_panel_adds_and_removes_the_product()
    {
        $product = $this->product();
        $sale = $this->sale();
        $this->actingAs($this->marketer);

        $panel = app(StorefrontSlots::class)->render('admin.product.sidebar', ['product' => $product]);
        $this->assertSame('ecomodular/flash-sales/ProductFlashSalePanel', $panel[0]['component'] ?? null);
        $this->assertSame($sale->id, $panel[0]['props']['sales'][0]['id']);

        $this->put(route(self::ROUTE.'product.update', $product), ['sale_id' => $sale->id, 'include' => true, 'discount_type' => 'percent', 'discount_value' => '30'])->assertSessionHasNoErrors();
        $this->assertSame('35.00', $this->price($product));

        $this->put(route(self::ROUTE.'product.update', $product), ['sale_id' => $sale->id, 'include' => false])->assertSessionHasNoErrors();
        app(SalePrices::class)->flush();
        $this->assertNull(app(SalePrices::class)->forVariant($product->variants()->firstOrFail(), app(StoreSettings::class)->currency()));

        // The edit page shows the panel.
        $this->get(route('admin.products.edit', $product))->assertInertia(fn (Assert $page) => $page->where('extensionPanels.0.component', 'ecomodular/flash-sales/ProductFlashSalePanel'));
    }

    public function test_the_panel_changes_only_the_chosen_sale()
    {
        $product = $this->product();
        $other = $this->sale(['name' => 'Other sale']);
        FlashSaleProduct::query()->create(['flash_sale_id' => $other->id, 'product_id' => $product->id, 'discount_type' => 'fixed', 'discount_value' => '3']);
        $sale = $this->sale();
        $ended = $this->sale(['starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()]);
        $this->actingAs($this->marketer);

        $this->put(route(self::ROUTE.'product.update', $product), ['sale_id' => $sale->id, 'include' => true])->assertSessionHasNoErrors();
        $this->put(route(self::ROUTE.'product.update', $product), ['sale_id' => $sale->id, 'include' => false])->assertSessionHasNoErrors();

        // The product's other sale and its own discount are untouched.
        $this->assertSame('3', (string) (float) FlashSaleProduct::query()->where('flash_sale_id', $other->id)->value('discount_value'));
        $panel = app(StorefrontSlots::class)->render('admin.product.sidebar', ['product' => $product]);
        $this->assertNotNull(collect($panel[0]['props']['sales'])->firstWhere('id', $other->id)['membership']);

        // Ended sales are not offered and cannot be joined.
        $this->put(route(self::ROUTE.'product.update', $product), ['sale_id' => $ended->id, 'include' => true])->assertSessionHasErrors('sale_id');
    }

    public function test_the_sale_page_lists_several_products()
    {
        $sale = $this->sale();
        foreach ([$this->product('10.00'), $this->product('20.00')] as $product) {
            FlashSaleProduct::query()->create(['flash_sale_id' => $sale->id, 'product_id' => $product->id]);
        }

        $this->actingAs($this->marketer)->get(route(self::ROUTE.'edit', $sale))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('sale.items', 2)->where('sale.items', fn ($items) => collect($items)->pluck('sale_price.amount')->sort()->values()->all() === ['8.00', '16.00']));
    }

    public function test_the_countdown_follows_the_sale_that_sets_the_price()
    {
        $product = $this->product('100.00');
        $soon = $this->sale(['name' => 'Ends soon', 'discount_value' => '10', 'ends_at' => now()->addHour()]);
        $best = $this->sale(['name' => 'Best deal', 'discount_value' => '30', 'ends_at' => now()->addWeek()]);
        foreach ([$soon, $best] as $sale) {
            FlashSaleProduct::query()->create(['flash_sale_id' => $sale->id, 'product_id' => $product->id]);
        }

        $this->get(route('storefront.product', $product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('product.variants.0.price.amount', '70.00')
            ->where('slots', fn ($slots) => collect($slots)->firstWhere('component', 'ecomodular/flash-sales/FlashSaleCountdown')['props']['name'] === 'Best deal'));
    }

    public function test_shoppers_see_the_sale_and_pay_the_sale_price()
    {
        $this->enableCheckout();
        $product = $this->product('50.00');
        $sale = $this->sale(['show_banner' => true]);
        FlashSaleProduct::query()->create(['flash_sale_id' => $sale->id, 'product_id' => $product->id]);

        $this->get(route('storefront.product', $product->slug))->assertInertia(fn (Assert $page) => $page
            ->where('product.variants.0.price.amount', '40.00')
            ->where('product.variants.0.compare_at_price.amount', '50.00')
            ->where('product.sale.badge', '−20%')
            ->where('slots', fn ($slots) => collect($slots)->contains('component', 'ecomodular/flash-sales/FlashSaleCountdown')));

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('slots', fn ($slots) => collect($slots)->contains(
            fn ($s) => $s['component'] === 'ecomodular/flash-sales/FlashSaleBanner' && $s['props']['products'][0]['price']['amount'] === '40.00'
        )));

        $this->addToCart($product->variants()->firstOrFail(), 1);
        // The price seen when adding is the sale price, so no "price changed" notice.
        $this->assertSame('40.0000', (string) CartItem::query()->latest('id')->value('seen_unit_price'));
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertSessionHasNoErrors();
        $this->assertSame('40.0000', Order::query()->latest('id')->firstOrFail()->items()->firstOrFail()->unit_price);
    }

    public function test_flash_sales_need_their_permission()
    {
        $product = $this->product();
        $this->actingAs($this->userWithRole('catalog-manager'))->get(route(self::ROUTE.'index'))->assertForbidden();
        $sale = $this->sale();
        $this->put(route(self::ROUTE.'product.update', $product), ['sale_id' => $sale->id, 'include' => true])->assertForbidden();
        $this->assertSame([], app(StorefrontSlots::class)->render('admin.product.sidebar', ['product' => $product]));
    }
}

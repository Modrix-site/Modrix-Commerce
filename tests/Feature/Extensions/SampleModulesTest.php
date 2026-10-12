<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * The bundled first-party modules, exercised from a copy of modules/.
 */
class SampleModulesTest extends TestCase
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
        Mail::fake();
    }

    private function state(string $name): ModuleState
    {
        return Extension::query()->where('name', $name)->firstOrFail()->state;
    }

    /**
     * Turns the copied reviews package back into its 1.0.0 release.
     */
    private function downgradeReviewsFilesTo100(): void
    {
        $dir = $this->modulesPath.'/ecomodular/product-reviews';
        File::move($dir.'/database/migrations/2026_10_15_100100_add_verified_purchase_to_product_reviews.php', $this->modulesPath.'/held-migration.php');
        $manifest = json_decode((string) File::get($dir.'/module.json'), true);
        $manifest['version'] = '1.0.0';
        File::put($dir.'/module.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function restoreReviewsFilesTo110(): void
    {
        File::copyDirectory(base_path('modules/ecomodular/product-reviews'), $this->modulesPath.'/ecomodular/product-reviews');
    }

    public function test_bundled_modules_are_valid_and_not_installed_by_default()
    {
        app(ModuleRepository::class)->sync();

        foreach (['ecomodular/product-reviews', 'ecomodular/sales-widget', 'ecomodular/order-alerts'] as $name) {
            $extension = Extension::query()->where('name', $name)->firstOrFail();
            $this->assertSame(ModuleState::Discovered, $extension->state, implode(' ', (array) $extension->errors));
        }
    }

    public function test_reviews_module_install_enable_configure_disable_update_lifecycle()
    {
        $this->downgradeReviewsFilesTo100();
        $owner = $this->owner();
        $customer = User::factory()->create(['name' => 'Ana Cruz']);
        $product = $this->sellable(product: ['name' => 'Teapot'])->product;

        // Install and enable v1.0.0.
        $this->modules->install('ecomodular/product-reviews', $owner);
        $this->modules->enable('ecomodular/product-reviews', true, $owner);
        $this->assertTrue(Schema::hasTable('product_reviews'));
        $this->assertFalse(Schema::hasColumn('product_reviews', 'verified_purchase'));

        // Configure: moderation on, short minimum length.
        $this->modules->configure('ecomodular/product-reviews', ['auto_approve' => '0', 'require_purchase' => '0', 'min_length' => '5'], $owner);

        // A guest sees the slot asking to sign in.
        $this->get(route('storefront.product', $product->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('slots.0.component', 'ecomodular/product-reviews/ProductReviews')
                ->where('slots.0.props.reason', 'login')
                ->where('slots.0.props.count', 0));

        // A review written while v1.0.0 was running (its schema has no verified_purchase column).
        $earlierId = DB::table('product_reviews')->insertGetId([
            'product_id' => $product->id, 'author_name' => 'Ben', 'rating' => 2, 'body' => 'Written under 1.0.0',
            'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
        ]);

        // Staff without the module permission cannot moderate; the owner can.
        $this->actingAs($this->userWithRole('catalog-manager'))->post(route('admin.ecomodular.product-reviews.approve', $earlierId))->assertForbidden();
        $this->actingAs($owner)->get(route('admin.ecomodular.product-reviews.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('module/ecomodular/product-reviews/admin/index', false)->has('reviews.data', 1));
        $this->actingAs($owner)->post(route('admin.ecomodular.product-reviews.approve', $earlierId))->assertRedirect();
        $this->get(route('storefront.product', $product->slug))
            ->assertInertia(fn (Assert $page) => $page->where('slots.0.props.count', 1));

        // Disable: the reviews stay in the database.
        $this->modules->disable('ecomodular/product-reviews', $owner);
        $this->assertSame(1, DB::table('product_reviews')->count());

        // New release on disk → update → the new column exists and data is intact.
        $this->restoreReviewsFilesTo110();
        app(ModuleRepository::class)->sync();
        $this->assertSame(ModuleState::UpdatePending, $this->state('ecomodular/product-reviews'));

        $updated = $this->modules->update('ecomodular/product-reviews', $owner);
        $this->assertSame('1.1.0', $updated->version);
        $this->assertSame(ModuleState::Disabled, $updated->state);
        $this->assertTrue(Schema::hasColumn('product_reviews', 'verified_purchase'));
        $this->assertSame(1, DB::table('product_reviews')->count());

        $this->modules->enable('ecomodular/product-reviews', true, $owner);
        $this->assertSame(ModuleState::Enabled, $this->state('ecomodular/product-reviews'));

        // v1.1.0 in use: a customer reviews; it waits for moderation; one review per customer.
        $this->app['auth']->forgetGuards();
        $this->post('/m/ecomodular/product-reviews/products/'.$product->slug.'/reviews')->assertRedirect(route('login'));
        $this->actingAs($customer)->post(route('ecomodular.product-reviews.store', $product->slug), ['rating' => 4, 'title' => 'Lovely', 'body' => 'Pours well.'])
            ->assertSessionHasNoErrors();
        $this->actingAs($customer)->post(route('ecomodular.product-reviews.store', $product->slug), ['rating' => 1, 'body' => 'Second try'])
            ->assertSessionHasErrors('body');
        $this->actingAs($customer)->get(route('storefront.product', $product->slug))
            ->assertInertia(fn (Assert $page) => $page->where('slots.0.props.count', 1)->where('slots.0.props.own.status', 'pending'));

        $newId = (int) DB::table('product_reviews')->where('user_id', $customer->id)->value('id');
        $this->actingAs($owner)->post(route('admin.ecomodular.product-reviews.approve', $newId))->assertRedirect();
        $this->get(route('storefront.product', $product->slug))
            ->assertInertia(fn (Assert $page) => $page
                ->where('slots.0.props.count', 2)
                ->where('slots.0.props.average', '3.0')
                ->where('slots.0.props.reviews.0.author', 'Ana'));
    }

    public function test_reviews_can_be_limited_to_buyers_and_mark_verified_purchases()
    {
        $this->modules->install('ecomodular/product-reviews');
        $this->modules->enable('ecomodular/product-reviews', true);
        $this->modules->configure('ecomodular/product-reviews', ['auto_approve' => '1', 'require_purchase' => '1', 'min_length' => '5']);

        $this->enableCheckout();
        $variant = $this->sellable('10.00', 5);
        $product = $variant->product;
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('ecomodular.product-reviews.store', $product->slug), ['rating' => 5, 'body' => 'Not bought yet'])
            ->assertSessionHasErrors('body');

        $this->actingAs($buyer);
        $this->addToCart($variant);
        $this->post(route('checkout.store'), $this->checkoutPayload(['email' => $buyer->email]))->assertRedirect();
        app(OrderWorkflow::class)->recordManualPayment(Order::query()->firstOrFail(), $this->owner());

        $this->actingAs($buyer)->post(route('ecomodular.product-reviews.store', $product->slug), ['rating' => 5, 'body' => 'Bought and loved it'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('product_reviews', ['user_id' => $buyer->id, 'status' => 'approved', 'verified_purchase' => true]);
    }

    public function test_sales_widget_appears_on_the_dashboard_for_report_viewers()
    {
        $this->modules->install('ecomodular/sales-widget');
        $this->modules->enable('ecomodular/sales-widget', true);

        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('widgets.0.component', 'ecomodular/sales-widget/SalesTrend')
                ->has('widgets.0.props.series', 7));

        $this->actingAs($this->userWithRole('catalog-manager'))->get(route('admin.dashboard'))
            ->assertInertia(fn (Assert $page) => $page->has('widgets', 0));
    }

    public function test_order_alerts_are_sent_once_per_paid_order_above_the_minimum()
    {
        $this->modules->install('ecomodular/order-alerts');
        $this->modules->enable('ecomodular/order-alerts', true);
        $this->modules->configure('ecomodular/order-alerts', ['recipient' => 'owner@example.com', 'minimum_total' => '20']);

        $this->enableCheckout();
        $big = $this->sellable('50.00', 5);
        $small = $this->sellable('5.00', 5);

        $this->addToCart($big);
        $this->post(route('checkout.store'), $this->checkoutPayload());
        $bigOrder = Order::query()->latest('id')->firstOrFail();

        $this->addToCart($small);
        $this->post(route('checkout.store'), $this->checkoutPayload());
        $smallOrder = Order::query()->latest('id')->firstOrFail();

        $owner = $this->owner();
        app(OrderWorkflow::class)->recordManualPayment($bigOrder, $owner);
        app(OrderWorkflow::class)->recordManualPayment($smallOrder, $owner);
        OrderPaid::dispatch($bigOrder->id); // duplicate delivery

        $this->assertSame([$bigOrder->id], DB::table('order_alert_deliveries')->pluck('order_id')->map(fn ($id) => (int) $id)->all());
    }

    public function test_disabled_modules_leave_no_trace_in_the_storefront()
    {
        $product = $this->sellable()->product;

        $this->get(route('storefront.product', $product->slug))
            ->assertInertia(fn (Assert $page) => $page->has('slots', 0));
        $this->post('/m/ecomodular/product-reviews/products/'.$product->slug.'/reviews')->assertNotFound();
    }
}

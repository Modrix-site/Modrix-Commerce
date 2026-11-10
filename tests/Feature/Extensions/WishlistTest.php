<?php

namespace Tests\Feature\Extensions;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Domains\Cart\Models\Cart;
use App\Models\User;
use EcoModular\Wishlist\Models\Wishlist;
use EcoModular\Wishlist\Models\WishlistItem;
use EcoModular\Wishlist\WishlistManager;
use EcoModular\Wishlist\WishlistSettings;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Feature tests for Wishlist & Save For Later:
 * - Module lifecycle & route registration
 * - Guest & user wishlist toggling
 * - Move to cart functionality
 * - Guest-to-user login sync
 * - Shareable wishlist access
 * - Admin analytics dashboard & settings
 */
class WishlistTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/wishlist';

    private ModuleManager $modules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(base_path('modules'), $this->modulesPath);
        $this->modules = app(ModuleManager::class);
        $this->modules->install(self::MODULE);
        $this->modules->enable(self::MODULE, true);
        $this->enableCheckout();
        config(['inertia.ssr.enabled' => false]);
    }

    private function state(string $name): ModuleState
    {
        return Extension::query()->where('name', $name)->firstOrFail()->state;
    }

    public function test_module_is_enabled_and_registers_routes_and_services(): void
    {
        $this->assertSame(ModuleState::Enabled, $this->state(self::MODULE));

        $this->assertTrue(app('router')->has('admin.modrixcommerce.wishlist.index'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.wishlist.settings'));
        $this->assertTrue(app('router')->has('admin.modrixcommerce.wishlist.settings.update'));
        $this->assertTrue(app('router')->has('modrixcommerce.wishlist.index'));
        $this->assertTrue(app('router')->has('modrixcommerce.wishlist.toggle'));
        $this->assertTrue(app('router')->has('modrixcommerce.wishlist.move_to_cart'));
        $this->assertTrue(app('router')->has('modrixcommerce.wishlist.destroy'));
        $this->assertTrue(app('router')->has('modrixcommerce.wishlist.share'));

        $this->assertInstanceOf(WishlistSettings::class, app(WishlistSettings::class));
        $this->assertInstanceOf(WishlistManager::class, app(WishlistManager::class));
    }

    public function test_guest_can_toggle_items_in_wishlist(): void
    {
        $a = $this->sellable('50.00');

        // 1. Toggle ON
        $response = $this->post(route('modrixcommerce.wishlist.toggle'), [
            'product_id' => $a->product_id,
        ]);

        $response->assertStatus(303);
        $token = session(WishlistManager::SESSION_TOKEN);
        $this->assertNotEmpty($token);

        $wishlist = Wishlist::query()->where('token', $token)->first();
        $this->assertNotNull($wishlist);
        $this->assertCount(1, $wishlist->items);
        $this->assertSame($a->product_id, $wishlist->items->first()->product_id);

        // 2. Toggle OFF (removes item)
        $this->post(route('modrixcommerce.wishlist.toggle'), [
            'product_id' => $a->product_id,
        ])->assertStatus(303);

        $this->assertCount(0, $wishlist->fresh()->items);
    }

    public function test_product_page_shows_wishlist_button_slot(): void
    {
        $a = $this->sellable('75.00');

        $this->get(route('storefront.product', $a->product->slug))
            ->assertOk()
            ->assertInertia(function (Assert $page) use ($a) {
                $slots = $page->toArray()['props']['placeSlots']['product.after_add_to_cart'] ?? [];
                $button = collect($slots)->firstWhere('component', 'modrixcommerce/wishlist/WishlistButton');
                $this->assertNotNull($button);
                $this->assertSame($a->product_id, $button['props']['product_id']);
                $this->assertFalse($button['props']['is_wishlisted']);
            });
    }

    public function test_move_to_cart_transfers_item_to_cart_and_removes_from_wishlist(): void
    {
        $a = $this->sellable('45.00');

        // Add to wishlist
        $this->post(route('modrixcommerce.wishlist.toggle'), [
            'product_id' => $a->product_id,
        ]);

        $token = session(WishlistManager::SESSION_TOKEN);
        $wishlist = Wishlist::query()->where('token', $token)->firstOrFail();
        $item = $wishlist->items()->firstOrFail();

        // Move to cart
        $this->post(route('modrixcommerce.wishlist.move_to_cart', $item->id))
            ->assertStatus(303);

        // Item should now be in cart
        $cart = Cart::query()->latest('id')->firstOrFail();
        $this->assertCount(1, $cart->items);
        $this->assertSame($a->id, $cart->items->first()->variant_id);

        // Item should be removed from wishlist
        $this->assertCount(0, $wishlist->fresh()->items);
    }

    public function test_guest_wishlist_merges_into_account_on_login(): void
    {
        $a = $this->sellable('60.00');

        // Guest adds item
        $this->post(route('modrixcommerce.wishlist.toggle'), [
            'product_id' => $a->product_id,
        ]);

        $token = session(WishlistManager::SESSION_TOKEN);
        $this->assertNotEmpty($token);

        // Registered user logs in
        $user = User::factory()->create();
        event(new Login('web', $user, false));

        // User's wishlist should now contain the item
        $userWishlist = Wishlist::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($userWishlist);
        $this->assertCount(1, $userWishlist->items);
        $this->assertSame($a->product_id, $userWishlist->items->first()->product_id);

        // Guest wishlist should be cleaned up
        $this->assertNull(Wishlist::query()->where('token', $token)->first());
    }

    public function test_shared_wishlist_page_renders(): void
    {
        $a = $this->sellable('90.00');
        $wishlist = Wishlist::create([
            'user_id' => null,
            'name' => "Alex's Wishlist",
            'share_token' => Wishlist::generateShareToken(),
            'is_public' => true,
        ]);

        WishlistItem::create([
            'wishlist_id' => $wishlist->id,
            'product_id' => $a->product_id,
            'variant_id' => $a->id,
        ]);

        $this->get(route('modrixcommerce.wishlist.share', $wishlist->share_token))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/wishlist/storefront/shared', false)
                ->where('wishlist.name', "Alex's Wishlist")
                ->has('items', 1)
                ->where('items.0.product_id', $a->product_id));
    }

    public function test_admin_analytics_and_settings(): void
    {
        $a = $this->sellable('120.00');

        $this->actingAs($this->userWithRole('catalog-manager'))
            ->get(route('admin.modrixcommerce.wishlist.index'))
            ->assertForbidden();

        $admin = $this->owner();

        // 1. Index
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.wishlist.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/wishlist/admin/index', false)
                ->has('stats')
                ->has('top_products')
                ->has('settings'));

        // 2. Settings page
        $this->actingAs($admin)
            ->get(route('admin.modrixcommerce.wishlist.settings'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/wishlist/admin/settings', false)
                ->has('settings'));

        // 3. Update settings
        $this->actingAs($admin)
            ->put(route('admin.modrixcommerce.wishlist.settings.update'), [
                'enabled' => true,
                'require_login' => false,
                'allow_sharing' => true,
                'button_label' => 'Add to Favorites',
            ])
            ->assertRedirect(route('admin.modrixcommerce.wishlist.settings'));

        $settings = app(WishlistSettings::class);
        $this->assertSame('Add to Favorites', $settings->buttonLabel());
    }
}

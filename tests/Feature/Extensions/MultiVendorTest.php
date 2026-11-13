<?php

namespace Tests\Feature\Extensions;

use App\Core\Catalog\ProductLabels;
use App\Core\Extensions\DashboardWidgets;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleRepository;
use App\Core\Extensions\ModuleState;
use App\Core\Extensions\StorefrontSlots;
use App\Domains\Catalog\Models\Product;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use EcoModular\MultiVendor\Models\MarketplaceVendor;
use EcoModular\MultiVendor\Models\MarketplaceVendorOrder;
use EcoModular\MultiVendor\Models\MarketplaceVendorPayout;
use EcoModular\MultiVendor\Models\MarketplaceVendorProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class MultiVendorTest extends TestCase
{
    use CommerceFixtures, ModuleFixtures, RefreshDatabase;

    private const MODULE = 'modrixcommerce/multi-vendor';

    private ModuleManager $modules;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        File::copyDirectory(
            base_path('modules/modrixcommerce/multi-vendor'),
            $this->modulesPath.'/modrixcommerce/multi-vendor'
        );

        $this->modules = app(ModuleManager::class);
        $this->modules->install(self::MODULE);
        $this->modules->enable(self::MODULE, true);
        config(['inertia.ssr.enabled' => false]);

        $this->owner = $this->owner();
    }

    public function test_module_installs_and_registers_capabilities_widgets_and_admin_dashboard(): void
    {
        $discovered = app(ModuleRepository::class)->find(self::MODULE);

        $this->assertNotNull($discovered);
        $this->assertSame(ModuleState::Enabled, $discovered->state);
        $this->assertSame('1.2.0', $discovered->manifest()->version);
        $this->assertContains('product.labels', $discovered->manifest()->capabilities);
        $this->assertContains('storefront.slots', $discovered->manifest()->capabilities);
        $this->assertContains('dashboard.widgets', $discovered->manifest()->capabilities);

        $this->actingAs($this->owner)
            ->get('/admin/m/modrixcommerce/multi-vendor')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/multi-vendor/admin/index', false)
                ->has('settings')
                ->has('stats')
                ->has('businessTypes')
                ->has('payoutMethods')
            );

        // Verify main Admin Dashboard widget is wired
        $widgets = app(DashboardWidgets::class)->forUser($this->owner);
        $mvWidget = collect($widgets)->firstWhere('component', self::MODULE.'/MultiVendorOverviewWidget');
        $this->assertNotNull($mvWidget);
    }

    public function test_vendor_registration_account_creation_login_and_kyb_business_verification_workflow(): void
    {
        // 1. Render public vendor registration & KYB onboarding form as a guest
        $this->get('/m/modrixcommerce/multi-vendor/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/multi-vendor/storefront/register', false)
                ->where('defaultCommissionRate', 10)
                ->where('isAuthenticated', false)
            );

        // 2. Guest submits vendor registration with account creation (email + password) + KYB + payout config
        $this->post('/m/modrixcommerce/multi-vendor/register', [
            'account_name' => 'Tariq Al-Mansoor',
            'store_name' => 'Atlas Artisan Goods',
            'email' => 'contact@atlasartisan.com',
            'password' => 'SellerPass123!',
            'password_confirmation' => 'SellerPass123!',
            'phone' => '+971501234567',
            'tagline' => 'Handcrafted leather & travel gear',
            'description' => 'Authentic handmade travel goods.',
            'business_type' => 'llc',
            'legal_business_name' => 'Atlas Artisan Trading LLC',
            'tax_id' => 'TRN-100293847500003',
            'trade_license_number' => 'DED-DXB-774829',
            'identity_document_ref' => 'EID-784-1990-9988776-1',
            'business_address' => 'Building 6, Dubai Design District',
            'city' => 'Dubai',
            'country_code' => 'AE',
            'payout_method' => 'bank_transfer',
            'auto_payout_enabled' => true,
            'bank_name' => 'Emirates NBD',
            'bank_account_name' => 'Atlas Artisan Trading LLC',
            'bank_account_number' => 'AE070331234567890123456',
            'bank_swift_code' => 'EBILAEAD',
        ])->assertRedirect('/m/modrixcommerce/multi-vendor/dashboard');

        $sellerUser = User::query()->where('email', 'contact@atlasartisan.com')->firstOrFail();
        $this->assertSame('Tariq Al-Mansoor', $sellerUser->name);

        $vendor = MarketplaceVendor::query()->where('slug', 'atlas-artisan-goods')->firstOrFail();
        $this->assertSame($sellerUser->id, $vendor->user_id);
        $this->assertSame(MarketplaceVendor::STATUS_PENDING, $vendor->status);
        $this->assertSame(MarketplaceVendor::VERIFICATION_PENDING, $vendor->verification_status);
        $this->assertSame('Atlas Artisan Trading LLC', $vendor->legal_business_name);
        $this->assertSame('DED-DXB-774829', $vendor->trade_license_number);
        $this->assertTrue($vendor->auto_payout_enabled);

        // 3. Seller signs out and signs back in via /m/modrixcommerce/multi-vendor/login
        $this->post('/m/modrixcommerce/multi-vendor/logout')
            ->assertRedirect('/m/modrixcommerce/multi-vendor/login');

        $this->get('/m/modrixcommerce/multi-vendor/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/multi-vendor/storefront/login', false)
            );

        $this->post('/m/modrixcommerce/multi-vendor/login', [
            'email' => 'contact@atlasartisan.com',
            'password' => 'SellerPass123!',
        ])->assertRedirect('/m/modrixcommerce/multi-vendor/dashboard');

        // 4. Admin requests more info on KYB first
        $this->actingAs($this->owner)
            ->put("/admin/m/modrixcommerce/multi-vendor/vendors/{$vendor->id}/verification", [
                'verification_status' => 'more_info_required',
                'verification_notes' => 'Please confirm trade license expiry date.',
                'rejection_reason' => 'Trade license copy needs renewal page.',
            ])
            ->assertRedirect();

        $vendor->refresh();
        $this->assertSame(MarketplaceVendor::VERIFICATION_MORE_INFO, $vendor->verification_status);

        // 5. Seller re-submits updated KYB credentials from Seller Portal
        $this->actingAs($sellerUser)
            ->post('/m/modrixcommerce/multi-vendor/dashboard/verification', [
                'business_type' => 'llc',
                'legal_business_name' => 'Atlas Artisan Trading LLC',
                'tax_id' => 'TRN-100293847500003',
                'trade_license_number' => 'DED-DXB-774829-RENEWED',
                'identity_document_ref' => 'EID-784-1990-9988776-1',
                'business_address' => 'Building 6, Dubai Design District',
                'city' => 'Dubai',
                'country_code' => 'AE',
            ])
            ->assertRedirect();

        $vendor->refresh();
        $this->assertSame(MarketplaceVendor::VERIFICATION_PENDING, $vendor->verification_status);
        $this->assertSame('DED-DXB-774829-RENEWED', $vendor->trade_license_number);

        // 6. Admin verifies the business (grants KYB Verified badge & approves store) and resets seller password
        $this->actingAs($this->owner)
            ->put("/admin/m/modrixcommerce/multi-vendor/vendors/{$vendor->id}/verification", [
                'verification_status' => 'verified',
                'verification_notes' => 'Trade license & TRN verified with DED registry.',
            ])
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->put("/admin/m/modrixcommerce/multi-vendor/vendors/{$vendor->id}/credentials", [
                'email' => 'contact@atlasartisan.com',
                'password' => 'UpdatedSellerPass99!',
                'account_name' => 'Tariq Al-Mansoor',
            ])
            ->assertRedirect();

        $vendor->refresh();
        $this->assertTrue($vendor->isVerified());
        $this->assertTrue($vendor->isApproved());
        $this->assertNotNull($vendor->verified_at);
    }

    public function test_vendor_product_catalog_creation_storefront_pages_product_labels_and_slots(): void
    {
        $sellerUser = User::factory()->create();

        // Create an approved & KYB-verified vendor
        $this->actingAs($this->owner)
            ->post('/admin/m/modrixcommerce/multi-vendor/vendors', [
                'store_name' => 'Nordic Home Studio',
                'email' => 'studio@nordichome.io',
                'business_type' => 'corporation',
                'legal_business_name' => 'Nordic Home Studio Inc.',
                'trade_license_number' => 'REG-99102',
                'city' => 'Stockholm',
                'country_code' => 'SE',
                'commission_rate' => 12,
                'payout_method' => 'wise',
                'payout_email' => 'payouts@nordichome.io',
                'approve_immediately' => true,
                'verify_immediately' => true,
            ])
            ->assertRedirect();

        $vendor = MarketplaceVendor::query()->where('slug', 'nordic-home-studio')->firstOrFail();
        $vendor->user_id = $sellerUser->id;
        $vendor->save();

        // 1. Vendor creates a product from their Seller Portal
        $this->actingAs($sellerUser)
            ->post('/m/modrixcommerce/multi-vendor/dashboard/products', [
                'name' => 'Minimalist Oak Desk Lamp',
                'sku' => 'NRD-LAMP-01',
                'price' => 85.00,
                'stock' => 15,
                'short_description' => 'Solid European oak desk lamp with warm LED.',
                'description' => 'Hand-turned solid oak desk lamp.',
            ])
            ->assertRedirect();

        $vp = MarketplaceVendorProduct::query()->where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertSame(MarketplaceVendorProduct::STATUS_APPROVED, $vp->approval_status);

        $product = Product::query()->findOrFail($vp->product_id);
        $this->assertSame('Minimalist Oak Desk Lamp', $product->localized('name') ?: $product->name);

        // 2. Verify ProductLabels injects the Verified Seller badge on storefront product cards
        $labels = app(ProductLabels::class)->labels($product);
        $vendorLabel = collect($labels)->firstWhere('key', 'vendor-'.$vendor->id);
        $this->assertNotNull($vendorLabel);
        $this->assertSame('✓ Nordic Home Studio', $vendorLabel['text']);
        $this->assertSame('featured', $vendorLabel['tone']);

        // 3. Verify ProductVendorBoxSlot renders on product.after_details
        $productSlots = app(StorefrontSlots::class)->render('product.after_details', ['product' => $product]);
        $vendorBoxSlot = collect($productSlots)->firstWhere('component', self::MODULE.'/ProductVendorBoxSlot');
        $this->assertNotNull($vendorBoxSlot);
        $this->assertSame('Nordic Home Studio', $vendorBoxSlot['props']['vendor']['store_name']);
        $this->assertTrue($vendorBoxSlot['props']['vendor']['is_verified']);

        // 4. Verify Public Marketplace Vendors Directory (`/m/modrixcommerce/multi-vendor/vendors`)
        $this->get('/m/modrixcommerce/multi-vendor/vendors?verified=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/multi-vendor/storefront/vendors', false)
                ->has('vendors', 1)
                ->where('vendors.0.store_name', 'Nordic Home Studio')
                ->where('vendors.0.is_verified', true)
            );

        // 5. Verify Dedicated Vendor Storefront Catalog (`/m/modrixcommerce/multi-vendor/vendors/nordic-home-studio`)
        $this->get('/m/modrixcommerce/multi-vendor/vendors/nordic-home-studio')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/multi-vendor/storefront/vendor-store', false)
                ->where('vendor.store_name', 'Nordic Home Studio')
                ->where('vendor.is_verified', true)
                ->has('products.data', 1)
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Minimalist Oak Desk Lamp')
                ->has('themeCollectionSections')
            );

        // 6. Verify Admin Product Sidebar Slot (`admin.product.sidebar`)
        $this->actingAs($this->owner);
        $adminSlots = app(StorefrontSlots::class)->render('admin.product.sidebar', ['product' => $product]);
        $sidebarSlot = collect($adminSlots)->firstWhere('component', self::MODULE.'/AdminProductVendorPanel');
        $this->assertNotNull($sidebarSlot);
        $this->assertSame($vendor->id, $sidebarSlot['props']['assignment']['vendor_id']);
    }

    public function test_order_splitting_commissions_and_store_to_vendor_payouts(): void
    {
        $this->enableCheckout();
        $sellerUser = User::factory()->create();

        // Create approved & verified vendor with 15% custom commission rate
        $vendor = MarketplaceVendor::query()->create([
            'user_id' => $sellerUser->id,
            'store_name' => 'Roast & Co',
            'slug' => 'roast-and-co',
            'email' => 'finance@roastco.com',
            'status' => MarketplaceVendor::STATUS_APPROVED,
            'verification_status' => MarketplaceVendor::VERIFICATION_VERIFIED,
            'business_type' => 'llc',
            'legal_business_name' => 'Roast & Co Coffee LLC',
            'trade_license_number' => 'TL-5541',
            'commission_rate' => 15.00,
            'payout_method' => 'bank_transfer',
            'bank_name' => 'HSBC',
            'bank_account_number' => 'AE990011223344',
            'auto_payout_enabled' => false,
            'approved_at' => now(),
            'verified_at' => now(),
        ]);

        // Create a sellable product ($100.00) and assign it to the vendor
        $variant = $this->sellable('100.00', 20);
        $this->actingAs($this->owner)
            ->post('/admin/m/modrixcommerce/multi-vendor/products/assign', [
                'product_id' => $variant->product_id,
                'vendor_id' => $vendor->id,
                'approval_status' => 'approved',
                'vendor_sku' => 'ROAST-1KG',
            ])
            ->assertRedirect();

        // Customer places an order for 2 units ($200.00 gross)
        $this->addToCart($variant, 2);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();

        // Verify automatic order split & 15% commission calculation ($200 gross -> $30 store commission -> $170 vendor earning)
        $vendorOrder = MarketplaceVendorOrder::query()
            ->where('order_id', $order->id)
            ->where('vendor_id', $vendor->id)
            ->firstOrFail();

        $this->assertEquals(200.00, (float) $vendorOrder->gross_amount);
        $this->assertEquals(15.00, (float) $vendorOrder->commission_rate);
        $this->assertEquals(30.00, (float) $vendorOrder->commission_amount);
        $this->assertEquals(170.00, (float) $vendorOrder->vendor_earning);
        $this->assertSame(MarketplaceVendorOrder::PAYOUT_CLEARED, $vendorOrder->payout_status);

        $vendor->refresh();
        $this->assertEquals(200.00, (float) $vendor->total_sales_amount);
        $this->assertEquals(30.00, (float) $vendor->total_commission_amount);
        $this->assertEquals(170.00, (float) $vendor->available_balance);

        // Seller updates order item fulfillment & tracking number from Seller Portal
        $this->actingAs($sellerUser)
            ->put("/m/modrixcommerce/multi-vendor/dashboard/orders/{$vendorOrder->id}/fulfillment", [
                'fulfillment_status' => 'shipped',
                'tracking_number' => 'DHL-9988776655',
            ])
            ->assertRedirect();

        $vendorOrder->refresh();
        $this->assertSame('shipped', $vendorOrder->fulfillment_status);
        $this->assertSame('DHL-9988776655', $vendorOrder->tracking_number);

        // Verify OrderVendorsSlot renders fulfillment & tracking on order confirmation page
        $orderSlots = app(StorefrontSlots::class)->render('order.confirmation', ['order' => $order]);
        $orderVendorSlot = collect($orderSlots)->firstWhere('component', self::MODULE.'/OrderVendorsSlot');
        $this->assertNotNull($orderVendorSlot);
        $this->assertSame('DHL-9988776655', $orderVendorSlot['props']['items'][0]['tracking_number']);

        // Seller requests a payout of $100.00 from the marketplace store
        $this->actingAs($sellerUser)
            ->post('/m/modrixcommerce/multi-vendor/dashboard/payouts/request', [
                'amount' => 100.00,
                'notes' => 'First partial payout request',
            ])
            ->assertRedirect();

        $payout = MarketplaceVendorPayout::query()->where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertEquals(100.00, (float) $payout->amount);
        $this->assertEquals(100.00, (float) $payout->net_amount);
        $this->assertSame(MarketplaceVendorPayout::STATUS_PENDING, $payout->status);
        $this->assertTrue($payout->requested_by_vendor);

        $vendor->refresh();
        $this->assertEquals(70.00, (float) $vendor->available_balance);

        // Admin marks the $100.00 payout as Paid with bank wire reference
        $this->actingAs($this->owner)
            ->put("/admin/m/modrixcommerce/multi-vendor/payouts/{$payout->id}/process", [
                'status' => 'paid',
                'transaction_reference' => 'HSBC-WIRE-20261112',
            ])
            ->assertRedirect();

        $payout->refresh();
        $vendor->refresh();
        $this->assertSame(MarketplaceVendorPayout::STATUS_PAID, $payout->status);
        $this->assertSame('HSBC-WIRE-20261112', $payout->transaction_reference);
        $this->assertEquals(100.00, (float) $vendor->paid_out_total);

        // Admin generates batch payouts for remaining $70.00 balance
        $this->actingAs($this->owner)
            ->post('/admin/m/modrixcommerce/multi-vendor/payouts/batch')
            ->assertRedirect();

        $vendor->refresh();
        $this->assertEquals(0.00, (float) $vendor->available_balance);
        $this->assertSame(2, MarketplaceVendorPayout::query()->where('vendor_id', $vendor->id)->count());

        // Test rejecting the second payout restores the $70.00 balance back to the vendor
        $secondPayout = MarketplaceVendorPayout::query()
            ->where('vendor_id', $vendor->id)
            ->where('id', '!=', $payout->id)
            ->firstOrFail();

        $this->actingAs($this->owner)
            ->put("/admin/m/modrixcommerce/multi-vendor/payouts/{$secondPayout->id}/process", [
                'status' => 'rejected',
                'notes' => 'IBAN verification needed',
            ])
            ->assertRedirect();

        $vendor->refresh();
        $this->assertEquals(70.00, (float) $vendor->available_balance);
    }

    public function test_automatic_payout_on_order_when_vendor_opts_in(): void
    {
        $this->enableCheckout();

        // Enable automatic_on_order payout mode in marketplace settings
        $this->actingAs($this->owner)
            ->put('/admin/m/modrixcommerce/multi-vendor/settings', [
                'enabled' => true,
                'allow_vendor_registration' => true,
                'auto_approve_vendors' => false,
                'require_business_verification' => true,
                'auto_approve_products' => true,
                'default_commission_rate' => 10,
                'payout_mode' => 'automatic_on_order',
                'min_payout_amount' => 50,
                'payout_fee_percent' => 2,
                'show_vendor_on_cards' => true,
                'show_vendor_box_on_product' => true,
            ])
            ->assertRedirect();

        $vendor = MarketplaceVendor::query()->create([
            'store_name' => 'AutoPay Electronics',
            'slug' => 'autopay-electronics',
            'email' => 'payouts@autopay.io',
            'status' => MarketplaceVendor::STATUS_APPROVED,
            'verification_status' => MarketplaceVendor::VERIFICATION_VERIFIED,
            'business_type' => 'corporation',
            'legal_business_name' => 'AutoPay Electronics Corp',
            'trade_license_number' => 'TL-AUTO-01',
            'payout_method' => 'stripe_connect',
            'payout_email' => 'acct_stripe_autopay',
            'auto_payout_enabled' => true,
            'approved_at' => now(),
            'verified_at' => now(),
        ]);

        $variant = $this->sellable('100.00', 10);
        $this->actingAs($this->owner)
            ->post('/admin/m/modrixcommerce/multi-vendor/products/assign', [
                'product_id' => $variant->product_id,
                'vendor_id' => $vendor->id,
                'approval_status' => 'approved',
            ])
            ->assertRedirect();

        // Customer buys 1 unit ($100.00 -> 10% commission = $10 -> $90 net earning >= $50 threshold)
        $this->addToCart($variant, 1);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertRedirect();

        $vendor->refresh();
        // Automatic payout should have been created for the $90.00 earning (with 2% payout fee = $1.80, net $88.20)
        $this->assertEquals(0.00, (float) $vendor->available_balance);

        $autoPayout = MarketplaceVendorPayout::query()->where('vendor_id', $vendor->id)->firstOrFail();
        $this->assertFalse($autoPayout->requested_by_vendor);
        $this->assertEquals(90.00, (float) $autoPayout->amount);
        $this->assertEquals(1.80, (float) $autoPayout->fee_amount);
        $this->assertEquals(88.20, (float) $autoPayout->net_amount);
    }

    public function test_amazon_style_theme_native_marketplace_catalog_filtering_and_sorting(): void
    {
        $sellerVerified = MarketplaceVendor::query()->create([
            'store_name' => 'Apex Verified Tech',
            'slug' => 'apex-verified-tech',
            'email' => 'tech@apex.example',
            'status' => MarketplaceVendor::STATUS_APPROVED,
            'verification_status' => MarketplaceVendor::VERIFICATION_VERIFIED,
            'rating' => 4.9,
            'approved_at' => now(),
            'verified_at' => now(),
        ]);

        $sellerStandard = MarketplaceVendor::query()->create([
            'store_name' => 'Budget Gadget Outlet',
            'slug' => 'budget-gadget-outlet',
            'email' => 'budget@outlet.example',
            'status' => MarketplaceVendor::STATUS_APPROVED,
            'verification_status' => MarketplaceVendor::VERIFICATION_UNVERIFIED,
            'rating' => 3.5,
            'approved_at' => now(),
        ]);

        $verifiedVariant = $this->sellable('199.00', 12);
        $verifiedProduct = $verifiedVariant->product;
        $verifiedProduct->update(['name' => 'Pro Noise Cancelling Headphones']);

        $standardVariant = $this->sellable('29.00', 8);
        $standardProduct = $standardVariant->product;
        $standardProduct->update(['name' => 'USB-C Braided Cable']);

        $directVariant = $this->sellable('59.00', 5);
        $directProduct = $directVariant->product;
        $directProduct->update(['name' => 'Direct Store Desk Pad']);

        $this->actingAs($this->owner)
            ->post('/admin/m/modrixcommerce/multi-vendor/products/assign', [
                'product_id' => $verifiedProduct->id,
                'vendor_id' => $sellerVerified->id,
                'approval_status' => 'approved',
            ])
            ->assertRedirect();

        $this->actingAs($this->owner)
            ->post('/admin/m/modrixcommerce/multi-vendor/products/assign', [
                'product_id' => $standardProduct->id,
                'vendor_id' => $sellerStandard->id,
                'approval_status' => 'approved',
            ])
            ->assertRedirect();

        // 1. All Products Catalog shows all 3 visible items sorted price_asc
        $this->get('/m/modrixcommerce/multi-vendor/catalog?sort=price_asc')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/multi-vendor/storefront/catalog', false)
                ->where('products.total', 3)
                ->where('products.data.0.name', 'USB-C Braided Cable')
                ->where('products.data.2.name', 'Pro Noise Cancelling Headphones')
                ->where('catalogStats.total_products', 3)
                ->where('catalogStats.sellers_count', 2)
                ->where('catalogStats.verified_seller_products_count', 1)
                ->has('filterVendors', 2)
                ->has('themeCollectionSections')
            );

        // 2. Verified Sellers Only filter shows only KYB-verified seller product
        $this->get('/m/modrixcommerce/multi-vendor/catalog?verified_only=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('module/modrixcommerce/multi-vendor/storefront/catalog', false)
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Pro Noise Cancelling Headphones')
            );

        // 3. Direct Store filter shows unassigned house product
        $this->get('/m/modrixcommerce/multi-vendor/catalog?vendor=direct')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Direct Store Desk Pad')
            );

        // 4. Search + 4★ & up filter matches Seller or Product keyword
        $this->get('/m/modrixcommerce/multi-vendor/catalog?q=Apex&min_rating=4')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('products.total', 1)
                ->where('products.data.0.name', 'Pro Noise Cancelling Headphones')
            );

        // 5. Verify core Storefront slots (catalog.before_products & home.after_featured) return marketplace data
        $beforeCatalog = app(StorefrontSlots::class)->render('catalog.before_products', []);
        $stripSlot = collect($beforeCatalog)->firstWhere('component', self::MODULE.'/MarketplaceCatalogStripSlot');
        $this->assertNotNull($stripSlot);
        $this->assertSame(2, $stripSlot['props']['sellersCount']);
        $this->assertSame(1, $stripSlot['props']['verifiedSellersCount']);

        $homeSlots = app(StorefrontSlots::class)->render('home.after_featured', []);
        $featuredSlot = collect($homeSlots)->firstWhere('component', self::MODULE.'/FeaturedVendorsBlock');
        $this->assertNotNull($featuredSlot);
        $this->assertCount(2, $featuredSlot['props']['featuredProducts']);
    }
}

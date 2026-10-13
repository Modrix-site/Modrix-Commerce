<?php

namespace Tests\Feature\Marketplace;

use App\Domains\Marketplace\MarketplaceCheckout;
use App\Domains\Marketplace\Models\LedgerEntry;
use App\Domains\Marketplace\Models\MarketplaceOrder;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceRelease;
use App\Domains\Marketplace\Models\Publisher;
use App\Domains\Marketplace\Models\SellerApplication;
use App\Domains\Marketplace\ReleasePublisher;
use App\Domains\Marketplace\SellerLedger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use LogicException;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Phase 8.4: third-party sellers, from application to payout.
 */
class SellerMarketplaceTest extends TestCase
{
    use MarketplaceFixtures, ModuleFixtures, RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        $this->setUpMarketplace();
        $this->owner = $this->owner();
        Http::fake(['api.stripe.com/*' => fn () => Http::response(['id' => 'cs_'.Str::random(10), 'url' => 'https://checkout.stripe.com/x'])]);
    }

    /**
     * @return array{0: User, 1: Publisher}
     */
    private function seller(string $slug = 'acme', string $commission = '30'): array
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('vendor.apply'), [
            'publisher_name' => ucfirst($slug).' Ltd', 'vendor_slug' => $slug, 'legal_name' => ucfirst($slug).' Limited',
            'country' => 'ES', 'payout_email' => "pay@{$slug}.test", 'support_email' => "help@{$slug}.test",
            'description' => 'We build shipping and gift modules for small shops.', 'terms' => '1',
        ])->assertRedirect(route('vendor.index'));

        $application = SellerApplication::query()->where('user_id', $user->id)->firstOrFail();
        $this->actingAs($this->owner)->post(route('admin.marketplace.sellers.approve', $application), ['commission_percent' => $commission])->assertRedirect();

        return [$user, Publisher::query()->where('slug', $slug)->firstOrFail()];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function productInput(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Hello Pro', 'slug' => 'hello-pro', 'extension_name' => 'acme/hello', 'type' => 'module',
            'price' => '49.00', 'updates_months' => 12, 'support_email' => 'help@acme.test',
        ], $overrides);
    }

    private function listedSellerProduct(User $seller): MarketplaceProduct
    {
        $this->actingAs($seller)->post(route('vendor.products.store'), $this->productInput())->assertRedirect();
        $product = MarketplaceProduct::query()->where('slug', 'hello-pro')->firstOrFail();

        $zip = $this->packageModule();
        $this->actingAs($seller)->post(route('vendor.releases.store', $product), [
            'package' => new UploadedFile($zip, 'hello.zip', 'application/zip', null, true),
        ])->assertSessionHasNoErrors();

        // Only the owner's review publishes the release and lists the product.
        $release = $product->releases()->firstOrFail();
        $this->assertSame(MarketplaceRelease::PENDING_REVIEW, $release->status);
        $this->get(route('marketplace.product', $product))->assertNotFound();
        app(ReleasePublisher::class)->review($release, true, 'Reviewed.', $this->owner);
        $product->forceFill(['status' => MarketplaceProduct::PUBLISHED])->save();

        return $product->fresh();
    }

    private function buy(MarketplaceProduct $product): MarketplaceOrder
    {
        $order = app(MarketplaceCheckout::class)->start(User::factory()->create(), $product, fn () => ['https://x.test/a', 'https://x.test/b'])['order'];
        app(MarketplaceCheckout::class)->fulfil($order, $order->money(), 'pi_'.$order->id);

        return $order->fresh();
    }

    public function test_seller_onboarding_publishing_sales_refunds_and_payouts()
    {
        [$seller, $publisher] = $this->seller(commission: '30');
        $this->actingAs($seller)->get(route('vendor.index'))->assertInertia(fn (Assert $page) => $page->component('storefront/vendor/dashboard')->where('publisher.slug', 'acme'));

        // Packages must use the seller's vendor name; sellers cannot list products themselves.
        $this->actingAs($seller)->post(route('vendor.products.store'), $this->productInput(['extension_name' => 'ecomodular/hello']))->assertSessionHasErrors('extension_name');
        $product = $this->listedSellerProduct($seller);
        $this->actingAs($seller)->put(route('vendor.products.update', $product), [...$this->productInput(), 'status' => 'published'])->assertSessionHasNoErrors();
        $this->actingAs($seller)->put(route('vendor.products.update', $product), [...$this->productInput(), 'status' => 'draft'])->assertSessionHasNoErrors();
        $product->forceFill(['status' => MarketplaceProduct::PUBLISHED])->save();
        $this->actingAs($seller)->put(route('vendor.products.update', $product->fresh()), [...$this->productInput(['slug' => 'hello-pro']), 'status' => 'archived'])->assertSessionHasErrors('status');

        // A sale: 49.00 with 30% commission → 34.30 owed.
        $ledger = app(SellerLedger::class);
        $first = $this->buy($product);
        $this->assertSame('34.30', $ledger->balance($publisher, 'USD')->toDisplayDecimal());
        $this->assertDatabaseHas('marketplace_ledger_entries', ['type' => 'sale', 'gross' => '49.0000', 'commission' => '14.7000', 'net' => '34.3000']);

        // Fulfilment is idempotent for the ledger too.
        app(MarketplaceCheckout::class)->fulfil($first, $first->money(), 'pi_again');
        $this->assertSame(1, LedgerEntry::query()->where('type', 'sale')->count());

        // A refund reverses it.
        app(MarketplaceCheckout::class)->markRefunded($first, 'Customer request', $this->owner);
        $this->assertSame('0.00', $ledger->balance($publisher, 'USD')->toDisplayDecimal());

        // Another sale, then a payout; payouts cannot exceed the balance.
        $this->buy($product);
        $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.marketplace.sellers.payouts.store', $publisher), ['amount' => '50.00', 'currency' => 'USD', 'reference' => 'SEPA-1'])
            ->assertSessionHasErrors('amount');
        $this->actingAs($this->owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.marketplace.sellers.payouts.store', $publisher), ['amount' => '34.30', 'currency' => 'USD', 'reference' => 'SEPA-1'])
            ->assertSessionHasNoErrors();
        $this->assertSame('0.00', $ledger->balance($publisher, 'USD')->toDisplayDecimal());

        $this->actingAs($seller)->get(route('vendor.index'))->assertInertia(fn (Assert $page) => $page
            ->where('totals.0.gross.amount', '49.00') // sale, refund, sale
            ->where('totals.0.net.amount', '0.00')
            ->has('ledger', 4)
            ->where('payouts.0.reference', 'SEPA-1'));

        // Platform products are not in any seller ledger; entries are append-only.
        $this->assertSame(0, LedgerEntry::query()->where('publisher_id', Publisher::platform()->id)->count());
        $this->expectException(LogicException::class);
        LedgerEntry::query()->firstOrFail()->forceFill(['net' => '0'])->save();
    }

    public function test_sellers_are_isolated_from_each_other()
    {
        [$acme] = $this->seller('acme');
        [$other] = $this->seller('other');
        $product = $this->listedSellerProduct($acme);

        $this->actingAs($other)->get(route('vendor.products.edit', $product))->assertNotFound();
        $this->actingAs($other)->put(route('vendor.products.update', $product), [...$this->productInput(), 'status' => 'draft'])->assertNotFound();
        $this->actingAs($other)->post(route('vendor.releases.store', $product), ['package' => UploadedFile::fake()->create('x.zip', 1, 'application/zip')])->assertNotFound();
        $this->actingAs($other)->get(route('vendor.index'))->assertInertia(fn (Assert $page) => $page->has('products', 0));

        // Customers without a seller account see the application form; they cannot reach seller routes.
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('vendor.index'))->assertInertia(fn (Assert $page) => $page->component('storefront/vendor/apply'));
        $this->actingAs($customer)->get(route('vendor.products.create'))->assertForbidden();

        // Only the owner manages sellers.
        $this->actingAs($acme)->get(route('admin.marketplace.sellers.index'))->assertForbidden();
    }

    public function test_suspending_a_seller_hides_products_and_pauses_uploads_but_keeps_licenses()
    {
        [$seller, $publisher] = $this->seller();
        $product = $this->listedSellerProduct($seller);
        $order = $this->buy($product);

        $this->actingAs($this->owner)->put(route('admin.marketplace.sellers.update', $publisher), ['action' => 'suspend', 'reason' => 'Complaints'])->assertRedirect();

        $this->get(route('marketplace.product', $product))->assertNotFound();
        $this->get(route('marketplace.index'))->assertInertia(fn (Assert $page) => $page->has('products', 0));
        $this->getJson(route('marketplace.api.catalog'))->assertJsonCount(0, 'products');
        $this->expectsBuyRefused($product);
        $this->actingAs($seller)->post(route('vendor.products.store'), $this->productInput(['slug' => 'other']))->assertForbidden();
        $this->assertSame('active', $order->items()->firstOrFail()->license?->status);

        $this->actingAs($this->owner)->put(route('admin.marketplace.sellers.update', $publisher), ['action' => 'reactivate'])->assertRedirect();
        $this->get(route('marketplace.product', $product))->assertOk();
    }

    private function expectsBuyRefused(MarketplaceProduct $product): void
    {
        $this->actingAs(User::factory()->create())->post(route('marketplace.buy', $product))->assertSessionHasErrors();
    }

    public function test_applications_are_validated_and_reviewed_once()
    {
        $user = User::factory()->create();
        $input = [
            'publisher_name' => 'Fake', 'legal_name' => 'Fake', 'country' => 'ES', 'payout_email' => 'a@b.test',
            'support_email' => 'a@b.test', 'description' => str_repeat('Long enough description. ', 3), 'terms' => '1',
        ];

        $this->actingAs($user)->post(route('vendor.apply'), [...$input, 'vendor_slug' => 'ecomodular'])->assertSessionHasErrors('vendor_slug');
        $this->actingAs($user)->post(route('vendor.apply'), [...$input, 'vendor_slug' => 'Bad Slug'])->assertSessionHasErrors('vendor_slug');
        $this->actingAs($user)->post(route('vendor.apply'), [...$input, 'vendor_slug' => 'fake', 'terms' => null])->assertSessionHasErrors('terms');
        $this->actingAs($user)->post(route('vendor.apply'), [...$input, 'vendor_slug' => 'fake'])->assertSessionHasNoErrors();
        $this->actingAs($user)->post(route('vendor.apply'), [...$input, 'vendor_slug' => 'fake2'])->assertSessionHasErrors('publisher_name');

        $application = SellerApplication::query()->firstOrFail();
        $this->actingAs($this->owner)->post(route('admin.marketplace.sellers.reject', $application), ['notes' => 'Not enough detail.'])->assertRedirect();
        $this->actingAs($this->owner)->post(route('admin.marketplace.sellers.approve', $application), ['commission_percent' => '20'])->assertSessionHasErrors('application');
        $this->actingAs($user)->get(route('vendor.index'))->assertInertia(fn (Assert $page) => $page->where('application.status', 'rejected')->where('application.review_notes', 'Not enough detail.'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketplace.seller_rejected']);
    }
}

<?php

namespace Tests\Feature\Downloads;

use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Downloads\Models\DownloadGrant;
use App\Domains\Orders\Mail\OrderMail;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class DownloadDeliveryTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    private Order $order;

    private User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Storage::fake('local');
        config(['inertia.ssr.enabled' => false]);

        $variant = $this->sellable('9.00', null, ['track_inventory' => false], ['is_digital' => true, 'name' => 'Field Guide']);
        Storage::disk('local')->put("downloads/{$variant->product_id}/guide.pdf", 'pdf');
        $variant->product->files()->create(['name' => 'Field Guide.pdf', 'disk' => 'local', 'path' => "downloads/{$variant->product_id}/guide.pdf", 'size' => 3, 'position' => 0]);

        $this->customer = User::factory()->create(['email' => 'buyer@example.com']);
        $this->actingAs($this->customer);
        $this->buy($variant);
        $this->order = app(OrderWorkflow::class)->recordManualPayment(Order::query()->latest('id')->firstOrFail(), $this->owner())->fresh();
    }

    private function buy(ProductVariant $variant): void
    {
        $this->addToCart($variant);
        $payload = $this->checkoutPayload();
        unset($payload['shipping_method']);
        $this->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();
    }

    private function grant(): DownloadGrant
    {
        return DownloadGrant::query()->firstOrFail();
    }

    public function test_paid_email_lists_download_links()
    {
        $html = (new OrderMail($this->order, 'paid'))->render();

        $this->assertStringContainsString('Field Guide.pdf', $html);
        $this->assertStringContainsString(route('storefront.downloads.show', $this->grant()->token), $html);
    }

    public function test_customer_order_page_lists_downloads()
    {
        $this->get(route('account.orders.show', $this->order))->assertInertia(fn (Assert $page) => $page
            ->where('order.downloads.0.file', 'Field Guide.pdf')
            ->where('order.downloads.0.url', route('storefront.downloads.show', $this->grant()->token))
            ->where('order.downloads.0.max_downloads', 5)
            ->where('order.downloads.0.usable', true));

        $this->get(route('checkout.complete', $this->order))->assertInertia(fn (Assert $page) => $page
            ->where('order.downloads.0.file', 'Field Guide.pdf'));
    }

    public function test_admin_can_reset_extend_revoke_and_needs_permission()
    {
        $grant = $this->grant();
        $grant->forceFill(['downloads' => 5, 'expires_at' => now()->addDay()])->save();
        $this->actingAs($this->owner());

        $this->get(route('admin.orders.show', $this->order))->assertInertia(fn (Assert $page) => $page
            ->where('order.downloads.0.id', $grant->id)
            ->where('order.downloads.0.usable', false));

        $this->post(route('admin.orders.downloads.reset', [$this->order, $grant]))->assertSessionHasNoErrors();
        $this->assertSame(0, $grant->fresh()->downloads);

        $this->post(route('admin.orders.downloads.extend', [$this->order, $grant]))->assertSessionHasNoErrors();
        $this->assertTrue($grant->fresh()->expires_at->between(now()->addDays(30), now()->addDays(32)));

        $this->post(route('admin.orders.downloads.revoke', [$this->order, $grant]))->assertSessionHasNoErrors();
        $this->assertNotNull($grant->fresh()->revoked_at);

        // Revoked links show without a URL to the customer.
        $this->actingAs($this->customer)->get(route('account.orders.show', $this->order))->assertInertia(fn (Assert $page) => $page
            ->where('order.downloads.0.url', null));

        $this->actingAs($this->userWithRole('catalog-manager'))
            ->post(route('admin.orders.downloads.reset', [$this->order, $grant]))->assertForbidden();
    }
}

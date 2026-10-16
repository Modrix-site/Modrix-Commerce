<?php

namespace Tests\Feature\Downloads;

use App\Domains\Catalog\Models\ProductFile;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Downloads\DownloadGrants;
use App\Domains\Downloads\DownloadSettings;
use App\Domains\Downloads\Models\DownloadGrant;
use App\Domains\Orders\Enums\FulfillmentStatus;
use App\Domains\Orders\Enums\OrderStatus;
use App\Domains\Orders\Events\OrderPaid;
use App\Domains\Orders\Models\Order;
use App\Domains\Orders\OrderWorkflow;
use App\Domains\Pricing\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommerceFixtures;
use Tests\TestCase;

class DownloadGrantsTest extends TestCase
{
    use CommerceFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->enableCheckout();
        Storage::fake('local');
    }

    private function digital(int $files = 1): ProductVariant
    {
        $variant = $this->sellable('9.00', null, ['track_inventory' => false], ['is_digital' => true]);

        foreach (range(1, $files) as $i) {
            Storage::disk('local')->put("downloads/{$variant->product_id}/file-{$i}.pdf", "content {$i}");
            $variant->product->files()->create(['name' => "Guide {$i}.pdf", 'disk' => 'local', 'path' => "downloads/{$variant->product_id}/file-{$i}.pdf", 'size' => 9, 'mime' => 'application/pdf', 'position' => $i]);
        }

        return $variant;
    }

    /**
     * @param  list<ProductVariant>  $variants
     */
    private function order(array $variants): Order
    {
        foreach ($variants as $variant) {
            $this->addToCart($variant);
        }

        $payload = $this->checkoutPayload();
        if (collect($variants)->every(fn (ProductVariant $v) => $v->product->is_digital)) {
            unset($payload['shipping_method']);
        }
        $this->post(route('checkout.store'), $payload)->assertSessionHasNoErrors();

        return Order::query()->latest('id')->firstOrFail();
    }

    private function pay(Order $order): Order
    {
        return app(OrderWorkflow::class)->recordManualPayment($order, $this->owner());
    }

    private function grant(): DownloadGrant
    {
        return DownloadGrant::query()->firstOrFail();
    }

    public function test_grants_created_only_after_payment_and_once()
    {
        $order = $this->order([$this->digital(2)]);
        $this->assertSame(0, DownloadGrant::query()->count());

        $this->pay($order);
        $this->assertSame(2, DownloadGrant::query()->count());
        $this->assertSame(40, strlen($this->grant()->token));
        $this->assertSame(5, $this->grant()->max_downloads);
        $this->assertTrue($this->grant()->expires_at->between(now()->addDays(29), now()->addDays(31)));

        OrderPaid::dispatch($order->id);
        $this->assertSame(2, DownloadGrant::query()->count());
    }

    public function test_digital_only_order_is_delivered_automatically()
    {
        $order = $this->pay($this->order([$this->digital()]))->fresh();
        $this->assertSame(OrderStatus::Completed, $order->status);
        $this->assertSame(FulfillmentStatus::Delivered, $order->fulfillment_status);

        $mixed = $this->pay($this->order([$this->digital(), $this->sellable('20.00', 5)]))->fresh();
        $this->assertSame(FulfillmentStatus::Unfulfilled, $mixed->fulfillment_status);
        $this->assertSame(1, DownloadGrant::query()->where('order_id', $mixed->id)->count());
    }

    public function test_download_streams_counts_and_logs()
    {
        $this->pay($this->order([$this->digital()]));
        $grant = $this->grant();

        $response = $this->get(route('storefront.downloads.show', $grant->token));
        $response->assertOk();
        $this->assertStringContainsString('attachment', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Guide 1.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertSame(1, $grant->fresh()->downloads);
        $this->assertSame(1, $grant->logs()->count());
    }

    public function test_limit_and_expiry_return_410()
    {
        $this->pay($this->order([$this->digital()]));
        $grant = $this->grant();

        foreach (range(1, 5) as $i) {
            $this->get(route('storefront.downloads.show', $grant->token))->assertOk();
        }
        $this->get(route('storefront.downloads.show', $grant->token))->assertStatus(410);

        $grant->forceFill(['downloads' => 0])->save();
        $this->travel(31)->days();
        $this->get(route('storefront.downloads.show', $grant->token))->assertStatus(410)->assertSee('expired');
    }

    public function test_a_file_name_with_slashes_still_downloads()
    {
        $this->pay($this->order([$this->digital()]));
        $grant = $this->grant();
        // Stored before names were validated, or set directly.
        ProductFile::query()->update(['name' => 'Part 1/2 AC\\DC.pdf']);

        $response = $this->get(route('storefront.downloads.show', $grant->token));
        $response->assertOk();
        $this->assertStringContainsString('Part 1-2 AC-DC.pdf', (string) $response->headers->get('content-disposition'));
        $this->assertSame(1, $grant->fresh()->downloads);
    }

    public function test_a_used_up_link_is_not_counted_again()
    {
        $this->pay($this->order([$this->digital()]));
        $grant = $this->grant();
        $grant->forceFill(['downloads' => 4])->save();
        $stale = $grant->fresh();
        // A parallel request takes the last download after this one checked the grant.
        DownloadGrant::query()->whereKey($grant->id)->update(['downloads' => 5]);

        $this->assertFalse(app(DownloadGrants::class)->consume($stale));
        $this->assertSame(5, $grant->fresh()->downloads);

        // Unlimited links always count.
        DownloadGrant::query()->whereKey($grant->id)->update(['max_downloads' => null]);
        $this->assertTrue(app(DownloadGrants::class)->consume($grant->fresh()));
        $this->assertSame(6, $grant->fresh()->downloads);
    }

    public function test_unknown_token_is_404()
    {
        $this->get(route('storefront.downloads.show', str_repeat('a', 40)))->assertNotFound();
        $this->get('/downloads/short')->assertNotFound();
    }

    public function test_refund_revokes_links()
    {
        $order = $this->pay($this->order([$this->digital()]));
        $grant = $this->grant();

        app(OrderWorkflow::class)->refund($order->fresh(), $this->owner(), Money::of('9.00', 'USD'), 'Changed mind');

        $this->assertNotNull($grant->fresh()->revoked_at);
        $this->get(route('storefront.downloads.show', $grant->token))->assertStatus(410);
    }

    public function test_cancel_revokes_links()
    {
        $order = $this->order([$this->digital()]);
        // Grants that exist for an order that is then cancelled stop working.
        $item = $order->items()->firstOrFail();
        $grant = DownloadGrant::query()->forceCreate(['order_id' => $order->id, 'order_item_id' => $item->id, 'product_file_id' => ProductFile::query()->value('id'), 'token' => str_repeat('b', 40)]);

        app(OrderWorkflow::class)->cancel($order, $this->owner(), 'Customer request');

        $this->assertNotNull($grant->fresh()->revoked_at);
    }

    public function test_a_deleted_file_gives_410_not_500()
    {
        $this->pay($this->order([$this->digital()]));
        $grant = $this->grant();
        Storage::disk('local')->delete(ProductFile::query()->value('path'));

        $this->get(route('storefront.downloads.show', $grant->token))->assertStatus(410);
    }

    public function test_settings_change_limit_and_expiry_for_new_grants()
    {
        app(DownloadSettings::class)->save(0, 0);
        $this->pay($this->order([$this->digital()]));

        $this->assertNull($this->grant()->max_downloads);
        $this->assertNull($this->grant()->expires_at);
        foreach (range(1, 7) as $i) {
            $this->get(route('storefront.downloads.show', $this->grant()->token))->assertOk();
        }

        $this->actingAs($this->owner())
            ->put(route('admin.settings.downloads.update'), ['download_limit' => 3, 'download_expiry_days' => 7])
            ->assertSessionHasNoErrors();
        $this->assertSame([3, 7], [app(DownloadSettings::class)->limit(), app(DownloadSettings::class)->expiryDays()]);
        $this->actingAs($this->userWithRole('catalog-manager'))
            ->put(route('admin.settings.downloads.update'), ['download_limit' => 1, 'download_expiry_days' => 1])
            ->assertForbidden();
    }
}

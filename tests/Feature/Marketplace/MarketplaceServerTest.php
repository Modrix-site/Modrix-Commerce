<?php

namespace Tests\Feature\Marketplace;

use App\Core\Marketplace\Signing;
use App\Domains\Marketplace\LicenseApi;
use App\Domains\Marketplace\LicenseApiException;
use App\Domains\Marketplace\LicenseManager;
use App\Domains\Marketplace\MarketplaceCheckout;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\MarketplaceOrder;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Marketplace\Models\MarketplaceRelease;
use App\Domains\Marketplace\ReleasePublisher;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;
use ZipArchive;

class MarketplaceServerTest extends TestCase
{
    use MarketplaceFixtures, ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        $this->setUpMarketplace();
    }

    private function zipWith(string $name, string $contents, ?int $unixMode = null): string
    {
        $zip = new ZipArchive;
        $path = $this->packagesPath.'/crafted-'.md5($name).'.zip';
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($name, $contents);
        if ($unixMode !== null) {
            $zip->setExternalAttributesName($name, ZipArchive::OPSYS_UNIX, $unixMode << 16);
        }
        $zip->close();

        return $path;
    }

    private function upload(string $zip, ?MarketplaceProduct $product = null): MarketplaceRelease
    {
        return app(ReleasePublisher::class)->upload($product ?? MarketplaceProduct::query()->first() ?? $this->product(), new UploadedFile($zip, 'p.zip', 'application/zip', null, true), null, $this->owner());
    }

    /**
     * @return array{license: License, key: string}
     */
    private function licensed(?MarketplaceProduct $product = null, ?User $buyer = null): array
    {
        return app(LicenseManager::class)->issue($product ?? $this->product(), $buyer ?? User::factory()->create());
    }

    public function test_unsafe_packages_are_rejected_at_upload()
    {
        $cases = [
            'traversal' => $this->zipWith('../escape.php', '<?php'),
            'absolute' => $this->zipWith('/etc/cron.d/x', 'x'),
            'symlink' => $this->zipWith('module.json', '/etc/passwd', 0120777),
            'not a module' => $this->zipWith('readme.txt', 'hello'),
        ];

        foreach ($cases as $label => $zip) {
            try {
                $this->upload($zip);
                $this->fail("{$label} package was accepted.");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('package', $e->errors(), $label);
            }
        }

        $this->assertSame(0, MarketplaceRelease::query()->count());
    }

    public function test_a_package_for_another_extension_or_an_older_version_is_rejected()
    {
        $product = $this->product(['extension_name' => 'acme/other']);

        $this->expectException(ValidationException::class);
        $this->upload($this->packageModule(), $product);
    }

    public function test_versions_must_increase()
    {
        $product = $this->product();
        $this->publish($product, $this->packageModule('1.1.0'));

        $this->expectException(ValidationException::class);
        $this->upload($this->packageModule('1.0.0'), $product);
    }

    public function test_releases_are_offered_only_after_review_and_their_metadata_is_signed()
    {
        $product = $this->product();
        $release = $this->upload($this->packageModule());
        ['key' => $key] = $this->licensed($product);
        $api = app(LicenseApi::class);
        $api->activate($key, 'instance-0000000000000001', 'shop.test', '0.4.0', null);

        $this->assertSame(MarketplaceRelease::PENDING_REVIEW, $release->status);
        $this->assertFalse($api->update($key, 'instance-0000000000000001', null, '0.4.0')['available']);

        app(ReleasePublisher::class)->review($release, true, null, $this->owner());
        $release->refresh();

        $this->assertTrue($api->update($key, 'instance-0000000000000001', null, '0.4.0')['available']);
        $this->assertTrue(Signing::verify($release->signed_metadata, (string) $release->signature, [$this->publicKey]));
        $this->assertSame($release->sha256, hash('sha256', (string) Storage::disk('local')->get($release->archive_path)));

        $tampered = [...$release->signed_metadata, 'sha256' => str_repeat('0', 64)];
        $this->assertFalse(Signing::verify($tampered, (string) $release->signature, [$this->publicKey]));
        $this->assertFalse(Signing::verify($release->signed_metadata, (string) $release->signature, [Signing::generateKeyPair()['public']]));
    }

    public function test_activation_limits_and_deactivation()
    {
        $this->publish($product = $this->product(['activation_limit' => 1]), $this->packageModule());
        ['key' => $key] = $this->licensed($product);
        $api = app(LicenseApi::class);

        $signed = $api->activate($key, 'instance-aaaaaaaaaaaaaaaa', 'one.test', '0.4.0', null);
        $this->assertTrue(Signing::verify($signed['token'], $signed['signature'], [$this->publicKey]));
        $this->assertSame('active', $signed['token']['status']);

        // Re-activating the same store does not use another slot.
        $api->activate($key, 'instance-aaaaaaaaaaaaaaaa', 'one.test', '0.4.0', null);

        try {
            $api->activate($key, 'instance-bbbbbbbbbbbbbbbb', 'two.test', '0.4.0', null);
            $this->fail('Activation limit not enforced.');
        } catch (LicenseApiException $e) {
            $this->assertSame('activation_limit', $e->errorCode);
        }

        $api->deactivate($key, 'instance-aaaaaaaaaaaaaaaa', null);
        $api->activate($key, 'instance-bbbbbbbbbbbbbbbb', 'two.test', '0.4.0', null);
        $this->assertDatabaseHas('license_events', ['action' => 'deactivated']);
    }

    public function test_download_tokens_are_short_lived_single_use_and_require_an_activated_instance()
    {
        $release = $this->publish($product = $this->product(), $this->packageModule());
        ['key' => $key] = $this->licensed($product);
        $api = app(LicenseApi::class);

        try {
            $api->requestDownload($key, 'instance-cccccccccccccccc', $release->id, null);
            $this->fail('Download without activation.');
        } catch (LicenseApiException $e) {
            $this->assertSame('not_activated', $e->errorCode);
        }

        $api->activate($key, 'instance-cccccccccccccccc', null, '0.4.0', null);
        $url = $api->requestDownload($key, 'instance-cccccccccccccccc', $release->id, null)['url'];
        $path = (string) parse_url($url, PHP_URL_PATH);

        $this->get($path)->assertOk()->assertHeader('X-Package-Sha256', $release->sha256);
        $this->get($path)->assertForbidden(); // single use

        $expired = $api->requestDownload($key, 'instance-cccccccccccccccc', $release->id, null)['url'];
        $this->travel(11)->minutes();
        $this->get((string) parse_url($expired, PHP_URL_PATH))->assertForbidden();
    }

    public function test_update_entitlement_ends_with_updates_until()
    {
        $product = $this->product();
        $this->publish($product, $this->packageModule('1.0.0'));
        ['license' => $license, 'key' => $key] = $this->licensed($product);
        $license->forceFill(['updates_until' => now()->addDay()])->save();
        $api = app(LicenseApi::class);
        $api->activate($key, 'instance-dddddddddddddddd', null, '0.4.0', null);

        $this->travel(2)->days();
        $this->publish($product, $this->packageModule('1.1.0'));

        $update = $api->update($key, 'instance-dddddddddddddddd', null, '0.4.0');
        $this->assertSame('1.0.0', $update['release']['version']);
        $this->assertFalse($api->update($key, 'instance-dddddddddddddddd', '1.0.0', '0.4.0')['available']);
    }

    public function test_free_products_issue_a_license_immediately()
    {
        $this->publish($product = $this->product(['price' => '0']), $this->packageModule());
        $buyer = User::factory()->create();

        $result = app(MarketplaceCheckout::class)->start($buyer, $product, fn () => ['https://x.test/ok', 'https://x.test/no']);

        $this->assertNull($result['redirect']);
        $this->assertSame(MarketplaceOrder::PAID, $result['order']->status);
        $this->assertSame(1, License::query()->where('user_id', $buyer->id)->count());
    }

    public function test_paid_purchase_issues_a_license_only_after_a_verified_payment()
    {
        $this->publish($product = $this->product(), $this->packageModule());
        $buyer = User::factory()->create();
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_mp_1', 'url' => 'https://checkout.stripe.com/mp'])]);

        $result = app(MarketplaceCheckout::class)->start($buyer, $product, fn ($o) => ["https://x.test/{$o->number}", 'https://x.test/no']);
        $this->assertSame('https://checkout.stripe.com/mp', $result['redirect']);
        $this->assertSame(0, License::query()->count());
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk_test_marketplace0000000000'));

        $session = ['id' => 'cs_mp_1', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 4900, 'currency' => 'usd', 'payment_intent' => 'pi_mp_1'];
        $event = ['id' => 'evt_mp_1', 'type' => 'checkout.session.completed', 'data' => ['object' => $session]];
        $this->postMarketplaceWebhook($event)->assertOk();
        $this->postMarketplaceWebhook($event)->assertOk()->assertJson(['status' => 'duplicate']);
        $this->postMarketplaceWebhook($event, 'whsec_wrong')->assertStatus(400);

        $order = $result['order']->fresh();
        $this->assertSame(MarketplaceOrder::PAID, $order->status);
        $this->assertSame(1, License::query()->where('user_id', $buyer->id)->count());
        $this->assertDatabaseMissing('orders', ['email' => $buyer->email]); // never a store order
    }

    public function test_a_payment_with_the_wrong_amount_issues_nothing()
    {
        $this->publish($product = $this->product(), $this->packageModule());
        Http::fake(['api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_mp_2', 'url' => 'https://checkout.stripe.com/mp'])]);
        app(MarketplaceCheckout::class)->start(User::factory()->create(), $product, fn () => ['https://x.test/a', 'https://x.test/b']);

        app(MarketplaceCheckout::class)->applySession(['id' => 'cs_mp_2', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 100, 'currency' => 'usd']);

        $this->assertSame(0, License::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'marketplace.payment_mismatch']);
    }

    public function test_refund_and_revocation_stop_downloads_updates_and_activations()
    {
        $release = $this->publish($product = $this->product(), $this->packageModule());
        $buyer = User::factory()->create();
        Http::fake([
            'api.stripe.com/v1/checkout/sessions' => Http::response(['id' => 'cs_mp_3', 'url' => 'https://checkout.stripe.com/mp']),
            'api.stripe.com/v1/refunds' => Http::response(['id' => 're_mp', 'status' => 'succeeded']),
        ]);
        $checkout = app(MarketplaceCheckout::class);
        $order = $checkout->start($buyer, $product, fn () => ['https://x.test/a', 'https://x.test/b'])['order'];
        $checkout->applySession(['id' => 'cs_mp_3', 'status' => 'complete', 'payment_status' => 'paid', 'amount_total' => 4900, 'currency' => 'usd', 'payment_intent' => 'pi_mp_3']);

        $license = License::query()->firstOrFail();
        $key = $license->key();
        $api = app(LicenseApi::class);
        $api->activate($key, 'instance-eeeeeeeeeeeeeeee', null, '0.4.0', null);
        $pending = $api->requestDownload($key, 'instance-eeeeeeeeeeeeeeee', $release->id, null)['url'];

        $checkout->refund($order->fresh(), 'Customer request', $this->owner());

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/v1/refunds') && $r['payment_intent'] === 'pi_mp_3');
        $this->assertSame(License::REFUNDED, $license->fresh()->status);
        $this->get((string) parse_url($pending, PHP_URL_PATH))->assertForbidden(); // pending link is dead
        $this->assertSame('refunded', $api->check($key, 'instance-eeeeeeeeeeeeeeee')['token']['status']);

        foreach ([fn () => $api->activate($key, 'instance-ffffffffffffffff', null, '0.4.0', null), fn () => $api->requestDownload($key, 'instance-eeeeeeeeeeeeeeee', $release->id, null), fn () => $api->update($key, 'instance-eeeeeeeeeeeeeeee', null, '0.4.0')] as $call) {
            try {
                $call();
                $this->fail('Refunded license still works.');
            } catch (LicenseApiException $e) {
                $this->assertSame('license_inactive', $e->errorCode);
            }
        }

        // Revocation by the owner behaves the same, and is reversible.
        ['license' => $other, 'key' => $otherKey] = $this->licensed($product);
        app(LicenseManager::class)->changeStatus($other, License::REVOKED, 'Chargeback', $this->owner());
        $this->expectException(LicenseApiException::class);
        $api->activate($otherKey, 'instance-gggggggggggggggg', null, '0.4.0', null);
    }

    public function test_api_responses_and_invalid_keys()
    {
        $this->publish($this->product(), $this->packageModule());

        $this->postJson('/api/marketplace/v1/licenses/activate', ['key' => 'EMK-AAAAA-AAAAA-AAAAA-AAAAA', 'instance_id' => 'instance-hhhhhhhhhhhhhhhh'])
            ->assertNotFound()->assertJson(['error' => 'invalid_key']);
        $this->postJson('/api/marketplace/v1/licenses/activate', ['key' => 'x', 'instance_id' => '../../etc'])->assertUnprocessable();
        $this->getJson('/api/marketplace/v1/catalog')->assertOk()->assertJsonPath('products.0.slug', 'hello-pro');
    }

    public function test_server_routes_do_not_exist_on_merchant_stores()
    {
        config(['ecomodular.marketplace.server.enabled' => false]);

        $this->getJson('/api/marketplace/v1/catalog')->assertNotFound();
        $this->get('/marketplace')->assertNotFound();
        $this->actingAs($this->owner())->get('/admin/marketplace/products')->assertNotFound();
    }

    public function test_customers_only_see_and_download_their_own_licenses()
    {
        $this->publish($product = $this->product(), $this->packageModule());
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        ['license' => $license, 'key' => $key] = $this->licensed($product, $alice);

        $this->actingAs($alice)->get('/account/marketplace')->assertOk()->assertSee($key);
        $this->actingAs($bob)->get('/account/marketplace')->assertOk()->assertDontSee($key);
        $this->actingAs($bob)->post("/account/marketplace/licenses/{$license->id}/download")->assertNotFound();
        $this->actingAs($alice)->post("/account/marketplace/licenses/{$license->id}/download")->assertRedirect();

        // From the portal (an Inertia request) the browser is sent to the single-use URL with a full visit.
        $this->actingAs($alice)->post("/account/marketplace/licenses/{$license->id}/download", [], ['X-Inertia' => 'true'])
            ->assertStatus(409)
            ->assertHeader('X-Inertia-Location');
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function postMarketplaceWebhook(array $event, string $secret = 'whsec_marketplace0000000000')
    {
        $payload = json_encode($event, JSON_THROW_ON_ERROR);
        $t = time();

        return $this->call('POST', '/webhooks/marketplace/stripe', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", $secret),
        ], $payload);
    }
}

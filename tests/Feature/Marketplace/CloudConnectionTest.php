<?php

namespace Tests\Feature\Marketplace;

use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Marketplace\TrustedKeys;
use App\Core\Settings\SecretSettings;
use App\Domains\Marketplace\MarketplaceCheckout;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Models\User;
use EcoModular\CloudConnector\CloudClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * Modrix Cloud Connector on the store: pairing with an account (PKCE),
 * the token never reaching the browser, one-click install, sync and
 * disconnect. Connection endpoints are faked; license activation, downloads
 * and verification use the real marketplace code in this application.
 */
class CloudConnectionTest extends TestCase
{
    use MarketplaceFixtures, ModuleFixtures, RefreshDatabase;

    private const TOKEN = 'ct_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abc';

    private MarketplaceProduct $product;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();
        config(['inertia.ssr.enabled' => false]);
        $this->installPlatform();
        $this->setUpModules();
        $this->enableBundledModule('modrixcommerce/cloud-connector');
        $this->setUpMarketplace();
        $this->product = $this->product(['price' => '0']);
        $this->publish($this->product, $this->packageModule('1.0.0'));
        // The account's license on the marketplace (as the cloud's claim endpoint would return).
        $buyer = User::factory()->create();
        app(MarketplaceCheckout::class)->start($buyer, $this->product, fn () => ['https://x.test/a', 'https://x.test/b']);
        $this->key = License::query()->where('user_id', $buyer->id)->firstOrFail()->key();
    }

    /** @param array<string, mixed> $license */
    private function fakeCloud(array $license = []): void
    {
        $api = rtrim((string) config('ecomodular.marketplace.url'), '/').'/api/marketplace/v1/';
        $row = ['identifier' => 'acme/hello', 'type' => 'module', 'name' => 'Hello Pro', 'key' => $this->key, 'status' => 'active', 'in_use_elsewhere' => false, 'active_domain' => null, ...$license];

        $this->routeMarketplaceToApp([
            $api.'connections/token' => fn (ClientRequest $r) => $r['code'] === 'good-code' && strlen((string) $r['code_verifier']) >= 43
                ? Http::response(['token' => self::TOKEN, 'account' => ['name' => 'Merchant', 'email' => 'merchant@example.com', 'store_name' => 'Jhay Store']])
                : Http::response(['error' => 'invalid_grant', 'message' => 'The connection request expired or was already used.'], 400),
            $api.'connections/licenses/claim' => Http::response(['license' => $row]),
            $api.'connections/licenses/release' => Http::response(['released' => true]),
            $api.'connections/licenses' => Http::response(['licenses' => [$row]]),
            $api.'connections/current' => Http::response(['revoked' => true]),
        ]);
    }

    /** Starts connecting and returns the state the store expects back. */
    private function startConnecting(): string
    {
        $location = $this->actingAs($this->owner())->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('admin.modrixcommerce.cloud-connector.connect'))
            ->assertRedirect()->headers->get('Location');
        parse_str((string) parse_url((string) $location, PHP_URL_QUERY), $query);

        $this->assertStringStartsWith(rtrim((string) config('ecomodular.marketplace.url'), '/').'/connect?', $location);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(43, strlen($query['code_challenge']));
        $this->assertArrayNotHasKey('code_verifier', $query);
        $this->assertSame(route('admin.modrixcommerce.cloud-connector.callback'), $query['return_url']);

        return $query['state'];
    }

    private function connect(): void
    {
        $state = $this->startConnecting();
        $this->get(route('admin.modrixcommerce.cloud-connector.callback', ['code' => 'good-code', 'state' => $state]))
            ->assertRedirect(route('admin.modrixcommerce.cloud-connector.index'));
    }

    public function test_a_store_without_configuration_uses_the_official_cloud_and_its_key(): void
    {
        config(['ecomodular.marketplace.url' => '', 'ecomodular.marketplace.trusted_keys' => []]);
        $client = app(CloudClient::class);

        $this->assertSame('https://cloud-apps.modrixcommerce.com', $client->baseUrl());
        $this->assertSame([CloudClient::TRUSTED_KEY], $client->trustedKeys());
    }

    public function test_the_hardcoded_key_is_the_live_modrix_cloud_signing_key(): void
    {
        // Settings → Licensing on cloud-apps.modrixcommerce.com shows key id e6064ae59fe8ad04.
        $raw = base64_decode(CloudClient::TRUSTED_KEY, true);

        $this->assertIsString($raw);
        $this->assertSame(SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, strlen($raw));
        $this->assertSame(TrustedKeys::OFFICIAL_KEY_ID, substr(hash('sha256', $raw), 0, 16));
        $this->assertSame('e6064ae59fe8ad04', TrustedKeys::OFFICIAL_KEY_ID);
        // Core updates and the connector trust the same key.
        $this->assertSame(TrustedKeys::OFFICIAL, CloudClient::TRUSTED_KEY);
    }

    public function test_configured_keys_are_trusted_alongside_the_official_one(): void
    {
        config(['ecomodular.marketplace.trusted_keys' => ['extra-key', CloudClient::TRUSTED_KEY]]);

        $this->assertSame([CloudClient::TRUSTED_KEY, 'extra-key'], app(CloudClient::class)->trustedKeys());
    }

    public function test_the_cloud_address_is_not_a_merchant_setting(): void
    {
        $manifest = json_decode((string) file_get_contents(base_path('modules/modrixcommerce/cloud-connector/module.json')), true);

        $this->assertArrayNotHasKey('settings', $manifest);
        $this->assertNotContains('settings', $manifest['capabilities']);
    }

    public function test_connecting_stores_the_token_encrypted_and_never_shows_it(): void
    {
        $this->fakeCloud();
        $this->connect();

        $this->assertSame(self::TOKEN, app(SecretSettings::class)->get(CloudClient::TOKEN_KEY));
        $this->assertDatabaseMissing('settings', ['value' => self::TOKEN]);

        $page = $this->get(route('admin.modrixcommerce.cloud-connector.index'))->assertOk();
        $page->assertInertia(fn (Assert $p) => $p->component('module/modrixcommerce/cloud-connector/index', false)
            ->where('connection.email', 'merchant@example.com'));
        $this->assertStringNotContainsString(self::TOKEN, (string) $page->getContent());

        // Later calls to Modrix Cloud carry the token.
        Http::assertSent(fn (ClientRequest $r) => str_contains($r->url(), '/catalog') && $r->hasHeader('Authorization', 'Bearer '.self::TOKEN));
    }

    public function test_the_callback_needs_the_state_it_started_with(): void
    {
        $this->fakeCloud();
        $this->startConnecting();

        $this->get(route('admin.modrixcommerce.cloud-connector.callback', ['code' => 'good-code', 'state' => 'forged-state-value']))
            ->assertRedirect(route('admin.modrixcommerce.cloud-connector.index'));
        $this->assertNull(app(SecretSettings::class)->get(CloudClient::TOKEN_KEY));

        // A refused approval connects nothing either.
        $state = $this->startConnecting();
        $this->get(route('admin.modrixcommerce.cloud-connector.callback', ['error' => 'access_denied', 'state' => $state]));
        $this->assertNull(app(SecretSettings::class)->get(CloudClient::TOKEN_KEY));
    }

    public function test_connecting_syncs_the_account_licenses_and_install_needs_no_key(): void
    {
        $this->fakeCloud();
        $this->connect();

        // The account's license was added and verified without anyone typing a key.
        $license = StoreLicense::query()->where('extension_name', 'acme/hello')->firstOrFail();
        $this->assertSame('active', $license->status);
        $this->assertFileExists($this->modulesPath.'/acme/hello/module.json');

        // One-click install of a catalog item works the same way.
        $license->delete();
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.modrixcommerce.cloud-connector.install-from-cloud'), ['identifier' => 'acme/hello'])
            ->assertRedirect(route('admin.modrixcommerce.cloud-connector.index'));
        $this->assertDatabaseHas('marketplace_licenses', ['extension_name' => 'acme/hello', 'status' => 'active']);
    }

    public function test_a_license_recorded_with_the_license_kind_as_type_is_repaired_from_the_signed_token(): void
    {
        // Older Modrix Cloud tokens signed "free"/"paid" as type, so no signed release
        // ("module"/"theme") matched: "The signed release is for a different product."
        $this->fakeCloud();
        $this->connect();
        $license = StoreLicense::query()->where('extension_name', 'acme/hello')->firstOrFail();

        $license->forceFill(['type' => 'free'])->save();
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.modrixcommerce.cloud-connector.licenses.check', $license));
        $this->assertSame('module', $license->fresh()->type);

        // Installing repairs it too, then verifies and installs the release.
        $license->forceFill(['type' => 'paid'])->save();
        File::deleteDirectory($this->modulesPath.'/acme/hello');
        $this->post(route('admin.modrixcommerce.cloud-connector.install', $license))->assertSessionHasNoErrors();
        $this->assertSame('module', $license->fresh()->type);
        $this->assertFileExists($this->modulesPath.'/acme/hello/module.json');
        $this->assertDatabaseHas('marketplace_update_history', ['extension_name' => 'acme/hello', 'action' => 'install', 'success' => true]);
    }

    public function test_a_license_used_on_another_store_needs_move_here(): void
    {
        $this->fakeCloud(['in_use_elsewhere' => true, 'active_domain' => 'old-shop.example']);
        $this->connect();
        // Sync leaves it alone.
        $this->assertDatabaseMissing('marketplace_licenses', ['extension_name' => 'acme/hello']);

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.modrixcommerce.cloud-connector.install-from-cloud'), ['identifier' => 'acme/hello'])
            ->assertSessionHasErrors(['identifier' => 'Your license for this package is active on old-shop.example. Choose Move here to use it on this store instead.']);

        $this->post(route('admin.modrixcommerce.cloud-connector.install-from-cloud'), ['identifier' => 'acme/hello', 'move_here' => '1'])
            ->assertSessionHasNoErrors();
        Http::assertSent(fn (ClientRequest $r) => str_ends_with($r->url(), 'connections/licenses/release') && $r['identifier'] === 'acme/hello');
        $this->assertDatabaseHas('marketplace_licenses', ['extension_name' => 'acme/hello']);
    }

    public function test_disconnecting_forgets_the_token_and_keeps_installed_licenses(): void
    {
        $this->fakeCloud();
        $this->connect();

        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.modrixcommerce.cloud-connector.disconnect'))
            ->assertRedirect(route('admin.modrixcommerce.cloud-connector.index'));

        $this->assertNull(app(SecretSettings::class)->get(CloudClient::TOKEN_KEY));
        Http::assertSent(fn (ClientRequest $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), 'connections/current'));
        $this->assertDatabaseHas('marketplace_licenses', ['extension_name' => 'acme/hello', 'status' => 'active']);
    }

    public function test_check_for_updates_installs_newer_verified_releases(): void
    {
        $this->fakeCloud();
        $this->connect();
        $this->assertStringContainsString('"version": "1.0.0"', (string) file_get_contents($this->modulesPath.'/acme/hello/module.json'));

        $this->publish($this->product, $this->packageModule('1.1.0'));
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.modrixcommerce.cloud-connector.updates.check'))
            ->assertRedirect(route('admin.modrixcommerce.cloud-connector.index'));
        $this->assertStringContainsString('"version": "1.1.0"', (string) file_get_contents($this->modulesPath.'/acme/hello/module.json'));
        $this->assertDatabaseHas('marketplace_update_history', ['extension_name' => 'acme/hello', 'action' => 'update', 'to_version' => '1.1.0', 'success' => true]);
    }

    public function test_an_installed_package_without_a_license_here_updates_through_the_account(): void
    {
        $this->fakeCloud();
        $this->connect();
        // Files stay (as for a bundled package); only the license is gone from this store.
        StoreLicense::query()->delete();

        $this->publish($this->product, $this->packageModule('1.1.0'));
        $this->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('admin.modrixcommerce.cloud-connector.install-from-cloud'), ['identifier' => 'acme/hello'])
            ->assertSessionHasNoErrors();

        $this->assertStringContainsString('"version": "1.1.0"', (string) file_get_contents($this->modulesPath.'/acme/hello/module.json'));
    }

    public function test_connecting_needs_install_rights_and_the_old_address_redirects(): void
    {
        $this->actingAs($this->userWithRole('analyst'))->get(route('admin.modrixcommerce.cloud-connector.connect'))->assertForbidden();
        $this->actingAs($this->owner())->get('/admin/extensions/marketplace')->assertRedirect(route('admin.modrixcommerce.cloud-connector.index'));
    }
}

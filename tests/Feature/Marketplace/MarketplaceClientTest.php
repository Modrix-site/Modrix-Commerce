<?php

namespace Tests\Feature\Marketplace;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Core\Extensions\PackageRoots;
use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Marketplace\Client\UpdateHistory;
use App\Core\Marketplace\PackageException;
use App\Core\Marketplace\Signing;
use App\Core\Operations\HealthCheck;
use App\Domains\Marketplace\LicenseManager;
use App\Domains\Marketplace\MarketplaceCheckout;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use EcoModular\CloudConnector\CloudClient;
use EcoModular\CloudConnector\CloudError;
use EcoModular\CloudConnector\PackageInstaller;
use EcoModular\CloudConnector\StoreLicenses;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CommerceFixtures;
use Tests\Support\MarketplaceFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

/**
 * The store side of the marketplace, end to end against the real server
 * code: buy → license → activate → download → verify → install → update →
 * roll back, plus tampering and outages.
 */
class MarketplaceClientTest extends TestCase
{
    use CommerceFixtures, MarketplaceFixtures, ModuleFixtures, RefreshDatabase;

    private MarketplaceProduct $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->installPlatform();
        $this->setUpModules();
        $this->enableBundledModule('modrixcommerce/cloud-connector');
        $this->setUpMarketplace();
        $this->product = $this->product(['price' => '0']);
        $this->publish($this->product, $this->packageModule('1.0.0'));
        $this->routeMarketplaceToApp();
    }

    private function buyKey(): string
    {
        $buyer = User::factory()->create();
        app(MarketplaceCheckout::class)->start($buyer, $this->product, fn () => ['https://x.test/a', 'https://x.test/b']);

        return License::query()->where('user_id', $buyer->id)->firstOrFail()->key();
    }

    public function test_buy_license_activate_download_install_update_and_roll_back()
    {
        $admin = $this->owner();
        $key = $this->buyKey();

        // Activate on this store: a verified, signed token is stored.
        $license = app(StoreLicenses::class)->activate($key, $admin);
        $this->assertSame('active', $license->status);
        $this->assertSame('acme/hello', $license->extension_name);
        $this->assertNull($license->available_release);
        $this->assertNull($license->last_error);
        $this->assertTrue(Signing::verify($license->token['token'], $license->token['signature'], [$this->publicKey]));

        // Activation pulls verified files; the module is not yet installed or enabled.
        $entry = UpdateHistory::query()->where('action', 'install')->firstOrFail();
        $this->assertTrue($entry->success);
        $this->assertFileExists($this->modulesPath.'/acme/hello/module.json');
        $this->assertSame(ModuleState::Discovered, Extension::query()->where('name', 'acme/hello')->firstOrFail()->state);

        // The admin installs and enables it through the normal lifecycle.
        $manager = app(ModuleManager::class);
        $manager->install('acme/hello', $admin);
        $manager->enable('acme/hello', true, $admin);

        // A new release is published; the store sees and applies the update.
        $this->publish($this->product, $this->packageModule('1.1.0', withSecondMigration: true));
        $license = app(StoreLicenses::class)->check($license->fresh());
        $this->assertNull($license->available_release);
        $this->assertNull($license->last_error);

        $update = UpdateHistory::query()->where('action', 'update')->latest('id')->firstOrFail();
        $this->assertSame('update', $update->action);
        $this->assertSame('1.0.0', $update->from_version);
        $this->assertFileExists((string) $update->backup_path);
        $this->assertSame(ModuleState::UpdatePending, Extension::query()->where('name', 'acme/hello')->firstOrFail()->state);

        $manager->update('acme/hello', $admin);
        $this->assertSame('1.1.0', Extension::query()->where('name', 'acme/hello')->firstOrFail()->version);

        // Restore the previous files (documented: a database restore undoes migrations).
        $rollback = app(PackageInstaller::class)->rollback($update, $admin);
        $this->assertSame('1.0.0', $rollback->to_version);
        $this->assertStringContainsString('"version": "1.0.0"', (string) File::get($this->modulesPath.'/acme/hello/module.json'));

        $this->assertSame(['install', 'update', 'rollback'], UpdateHistory::query()->orderBy('id')->pluck('action')->all());
        $this->assertDatabaseHas('license_events', ['action' => 'downloaded']);
    }

    public function test_a_redeploy_that_removed_the_files_is_restored_from_modrix_cloud()
    {
        $admin = $this->owner();
        app(StoreLicenses::class)->activate($this->buyKey(), $admin);
        $manager = app(ModuleManager::class);
        $manager->install('acme/hello', $admin);
        $manager->enable('acme/hello', true, $admin);
        $this->publish($this->product, $this->packageModule('1.1.0', withSecondMigration: true));

        // A redeploy replaced the folder: the files are gone, the record stays.
        File::deleteDirectory($this->modulesPath.'/acme/hello');
        PackageRoots::forget();

        $this->artisan('modrix:cloud:restore')
            ->expectsOutputToContain('acme/hello: restored from Modrix Cloud.')
            ->assertSuccessful();

        // The newest release came back, its update ran and the module is enabled again.
        $this->assertFileExists($this->modulesPath.'/acme/hello/module.json');
        $module = Extension::query()->where('name', 'acme/hello')->firstOrFail();
        $this->assertSame(ModuleState::Enabled, $module->state);
        $this->assertSame('1.1.0', $module->version);

        // Nothing left to do; an uninstalled module is never brought back.
        $this->artisan('modrix:cloud:restore')->expectsOutput('Nothing to restore.')->assertSuccessful();
        $manager->disable('acme/hello', $admin);
        $manager->delete('acme/hello', 'acme/hello', $admin);
        $this->artisan('modrix:cloud:restore')->expectsOutput('Nothing to restore.')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($this->modulesPath.'/acme/hello');
    }

    public function test_a_tampered_package_is_refused_and_the_store_is_untouched()
    {
        $key = $this->buyKey();
        $release = $this->product->latestPublishedRelease();

        // Someone replaces the archive on the marketplace storage after signing.
        Storage::disk('local')->put($release->archive_path, (string) Storage::disk('local')->get($release->archive_path).'x');
        $release->forceFill(['size' => $release->size + 1])->save();
        $signed = $release->signed_metadata;
        $signed['size'] = $release->size; // the attacker cannot re-sign
        $release->forceFill(['signed_metadata' => $signed])->save();
        $license = app(StoreLicenses::class)->activate($key, $this->owner());
        $this->assertStringContainsString('not signed by a trusted', $license->last_error);

        try {
            app(PackageInstaller::class)->install($license, $this->owner());
            $this->fail('Tampered package installed.');
        } catch (PackageException $e) {
            $this->assertStringContainsString('not signed by a trusted', $e->getMessage());
        }

        $this->assertDirectoryDoesNotExist($this->modulesPath.'/acme/hello');
        $this->assertFalse(UpdateHistory::query()->firstOrFail()->success);
    }

    public function test_a_corrupted_download_fails_the_checksum()
    {
        $key = $this->buyKey();
        $release = $this->product->latestPublishedRelease();
        $bytes = (string) Storage::disk('local')->get($release->archive_path);
        Storage::disk('local')->put($release->archive_path, substr($bytes, 0, -1).(substr($bytes, -1) === 'A' ? 'B' : 'A')); // same size
        $license = app(StoreLicenses::class)->activate($key, $this->owner());
        $this->assertStringContainsString('signed checksum', $license->last_error);

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('signed checksum');
        app(PackageInstaller::class)->install($license, $this->owner());
    }

    public function test_answers_signed_by_an_unknown_key_are_refused()
    {
        config(['ecomodular.marketplace.trusted_keys' => [Signing::generateKeyPair()['public']]]);

        $this->expectException(CloudError::class);
        $this->expectExceptionMessage('could not be verified');
        app(StoreLicenses::class)->activate($this->buyKey(), $this->owner());
    }

    public function test_a_refunded_license_is_reported_and_blocks_updates_but_installed_files_stay()
    {
        $admin = $this->owner();
        $key = $this->buyKey();
        $license = app(StoreLicenses::class)->activate($key, $admin);
        $this->assertNull($license->last_error);

        app(LicenseManager::class)->changeStatus(License::findByKey($key), License::REFUNDED, 'Refund', $admin);
        $license = app(StoreLicenses::class)->check($license->fresh());

        $this->assertSame('refunded', $license->status);
        $this->assertNull($license->available_release);
        $this->assertFileExists($this->modulesPath.'/acme/hello/module.json');

        $this->expectException(CloudError::class);
        app(PackageInstaller::class)->install($license, $admin);
    }

    public function test_marketplace_outage_does_not_affect_the_store()
    {
        $license = app(StoreLicenses::class)->activate($this->buyKey(), $this->owner());
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        app(CloudClient::class)->forgetCatalog();

        // Checking records the error and changes nothing else.
        $checked = app(StoreLicenses::class)->check($license->fresh());
        $this->assertSame('active', $checked->status);
        $this->assertNotNull($checked->last_error);
        $this->assertFalse($checked->needsVerification()); // still within the token validity

        // The admin page renders with a notice.
        $this->actingAs($this->owner())->get(route('admin.modrixcommerce.cloud-connector.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('catalogError', fn ($e) => str_contains((string) $e, 'could not be reached'))->has('licenses', 1));

        // Storefront and checkout never call the marketplace.
        Mail::fake();
        $this->enableCheckout();
        $variant = $this->sellable();
        $this->get('/')->assertOk();
        $this->addToCart($variant);
        $this->post(route('checkout.store'), $this->checkoutPayload())->assertRedirect();
        $this->assertSame(1, Order::query()->count());
    }

    public function test_security_advisories_reach_affected_stores_until_they_update()
    {
        $admin = $this->owner();
        $license = app(StoreLicenses::class)->activate($this->buyKey(), $admin);
        $this->assertNull($license->last_error);

        // The publisher publishes an advisory for versions before 1.1.0.
        $this->actingAs($admin)->post(route('admin.marketplace.advisories.store', $this->product), [
            'severity' => 'critical', 'title' => 'Stored XSS in notes', 'body' => 'Update now.',
            'affected_versions' => '<1.1.0', 'fixed_version' => '1.1.0',
        ])->assertRedirect();
        $this->actingAs($admin)->post(route('admin.marketplace.advisories.store', $this->product), [
            'severity' => 'high', 'title' => 'x', 'body' => 'x', 'affected_versions' => 'not a constraint',
        ])->assertSessionHasErrors('affected_versions');

        // The store learns it from the signed token on its next check.
        $license = app(StoreLicenses::class)->check($license->fresh());
        $this->assertCount(1, $license->advisoriesFor('1.0.0'));
        $this->assertSame([], $license->advisoriesFor('1.1.0'));
        $this->actingAs($admin)->get(route('admin.modrixcommerce.cloud-connector.index'))
            ->assertInertia(fn (Assert $page) => $page->where('licenses.0.advisories.0.title', 'Stored XSS in notes'));
        $health = collect(app(HealthCheck::class)->run())->firstWhere('key', 'advisories');
        $this->assertSame('warning', $health['status']);

        // After updating to the fixed version the warning is gone.
        $this->publish($this->product, $this->packageModule('1.1.0', withSecondMigration: true));
        $checked = app(StoreLicenses::class)->check($license->fresh());
        $this->assertNull($checked->last_error);
        $this->actingAs($admin)->get(route('admin.modrixcommerce.cloud-connector.index'))
            ->assertInertia(fn (Assert $page) => $page->has('licenses.0.advisories', 0));
        $this->assertSame('ok', collect(app(HealthCheck::class)->run())->firstWhere('key', 'advisories')['status']);
    }

    public function test_forged_advisories_are_refused()
    {
        $license = app(StoreLicenses::class)->activate($this->buyKey(), $this->owner());
        $signed = $license->token;
        $forged = $signed['token'];
        $forged['advisories'] = [['id' => 99, 'severity' => 'critical', 'title' => 'Install my "fix" from evil.test', 'body' => 'x', 'affected_versions' => '*', 'fixed_version' => null, 'published_at' => now()->toIso8601String()]];

        // A man in the middle answers with the forged advisory and the original signature.
        Http::swap(new HttpFactory);
        Http::fake(['*' => Http::response(['token' => $forged, 'signature' => $signed['signature']])]);
        $license = app(StoreLicenses::class)->check($license->fresh());

        $this->assertStringContainsString('could not be verified', (string) $license->last_error);
        $this->assertSame([], $license->advisoriesFor('1.0.0'));
    }

    public function test_grace_period_warning_after_the_token_expires()
    {
        $license = app(StoreLicenses::class)->activate($this->buyKey(), $this->owner());
        $this->assertFalse($license->needsVerification());

        $this->travel(30 + 14 + 1)->days();
        $this->assertTrue($license->fresh()->needsVerification());
    }

    public function test_client_admin_permissions()
    {
        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.modrixcommerce.cloud-connector.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('store-manager'))->post(route('admin.modrixcommerce.cloud-connector.licenses.activate'), ['key' => 'EMK-AAAAA-AAAAA-AAAAA-AAAAA'])->assertForbidden();

        $license = StoreLicense::query()->forceCreate([
            'product_slug' => 'x', 'product_name' => 'X', 'extension_name' => 'acme/x', 'type' => 'module',
            'key_encrypted' => encrypt('k', false), 'key_hint' => 'AAAAA', 'status' => 'active',
        ]);
        $this->actingAs($this->owner())->post(route('admin.modrixcommerce.cloud-connector.install', $license))->assertRedirect(route('password.confirm'));
    }
}

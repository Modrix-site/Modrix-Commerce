<?php

namespace Tests\Feature\Marketplace;

use App\Core\Extensions\Models\Extension;
use App\Core\Extensions\ModuleManager;
use App\Core\Extensions\ModuleState;
use App\Core\Marketplace\Client\MarketplaceError;
use App\Core\Marketplace\Client\PackageInstaller;
use App\Core\Marketplace\Client\StoreLicense;
use App\Core\Marketplace\Client\StoreLicenses;
use App\Core\Marketplace\Client\UpdateHistory;
use App\Core\Marketplace\PackageException;
use App\Core\Marketplace\Signing;
use App\Domains\Marketplace\LicenseManager;
use App\Domains\Marketplace\MarketplaceCheckout;
use App\Domains\Marketplace\Models\License;
use App\Domains\Marketplace\Models\MarketplaceProduct;
use App\Domains\Orders\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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
        $this->assertSame('1.0.0', $license->available_release['version']);
        $this->assertTrue(Signing::verify($license->token['token'], $license->token['signature'], [$this->publicKey]));

        // Install: verified files land in modules/, nothing is installed in the database or enabled.
        $entry = app(PackageInstaller::class)->install($license, $admin);
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
        $this->assertSame('1.1.0', $license->available_release['version']);

        $update = app(PackageInstaller::class)->install($license, $admin);
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

    public function test_a_tampered_package_is_refused_and_the_store_is_untouched()
    {
        $license = app(StoreLicenses::class)->activate($this->buyKey(), $this->owner());
        $release = $this->product->latestPublishedRelease();

        // Someone replaces the archive on the marketplace storage after signing.
        Storage::disk('local')->put($release->archive_path, (string) Storage::disk('local')->get($release->archive_path).'x');
        $release->forceFill(['size' => $release->size + 1])->save();
        $signed = $release->signed_metadata;
        $signed['size'] = $release->size; // the attacker cannot re-sign
        $release->forceFill(['signed_metadata' => $signed])->save();

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
        $license = app(StoreLicenses::class)->activate($this->buyKey(), $this->owner());
        $release = $this->product->latestPublishedRelease();
        $bytes = (string) Storage::disk('local')->get($release->archive_path);
        Storage::disk('local')->put($release->archive_path, substr($bytes, 0, -1).(substr($bytes, -1) === 'A' ? 'B' : 'A')); // same size

        $this->expectException(PackageException::class);
        $this->expectExceptionMessage('signed checksum');
        app(PackageInstaller::class)->install($license, $this->owner());
    }

    public function test_answers_signed_by_an_unknown_key_are_refused()
    {
        config(['ecomodular.marketplace.trusted_keys' => [Signing::generateKeyPair()['public']]]);

        $this->expectException(MarketplaceError::class);
        $this->expectExceptionMessage('could not be verified');
        app(StoreLicenses::class)->activate($this->buyKey(), $this->owner());
    }

    public function test_a_refunded_license_is_reported_and_blocks_updates_but_installed_files_stay()
    {
        $admin = $this->owner();
        $key = $this->buyKey();
        $license = app(StoreLicenses::class)->activate($key, $admin);
        app(PackageInstaller::class)->install($license, $admin);

        app(LicenseManager::class)->changeStatus(License::findByKey($key), License::REFUNDED, 'Refund', $admin);
        $license = app(StoreLicenses::class)->check($license->fresh());

        $this->assertSame('refunded', $license->status);
        $this->assertNull($license->available_release);
        $this->assertFileExists($this->modulesPath.'/acme/hello/module.json');

        $this->expectException(MarketplaceError::class);
        app(PackageInstaller::class)->install($license, $admin);
    }

    public function test_marketplace_outage_does_not_affect_the_store()
    {
        $license = app(StoreLicenses::class)->activate($this->buyKey(), $this->owner());
        Http::fake(fn () => throw new ConnectionException('Connection refused'));
        cache()->forget('ecomodular.marketplace.catalog');

        // Checking records the error and changes nothing else.
        $checked = app(StoreLicenses::class)->check($license->fresh());
        $this->assertSame('active', $checked->status);
        $this->assertNotNull($checked->last_error);
        $this->assertFalse($checked->needsVerification()); // still within the token validity

        // The admin page renders with a notice.
        $this->actingAs($this->owner())->get(route('admin.marketplace-client.index'))
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

    public function test_grace_period_warning_after_the_token_expires()
    {
        $license = app(StoreLicenses::class)->activate($this->buyKey(), $this->owner());
        $this->assertFalse($license->needsVerification());

        $this->travel(30 + 14 + 1)->days();
        $this->assertTrue($license->fresh()->needsVerification());
    }

    public function test_client_admin_permissions()
    {
        $this->actingAs($this->userWithRole('store-manager'))->get(route('admin.marketplace-client.index'))->assertForbidden();
        $this->actingAs($this->userWithRole('store-manager'))->post(route('admin.marketplace-client.licenses.activate'), ['key' => 'EMK-AAAAA-AAAAA-AAAAA-AAAAA'])->assertForbidden();

        $license = StoreLicense::query()->forceCreate([
            'product_slug' => 'x', 'product_name' => 'X', 'extension_name' => 'acme/x', 'type' => 'module',
            'key_encrypted' => encrypt('k', false), 'key_hint' => 'AAAAA', 'status' => 'active',
        ]);
        $this->actingAs($this->owner())->post(route('admin.marketplace-client.install', $license))->assertRedirect(route('password.confirm'));
    }
}

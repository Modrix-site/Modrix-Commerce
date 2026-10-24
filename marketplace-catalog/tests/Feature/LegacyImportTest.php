<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Marketplace\Models\License;
use Marketplace\Models\Order;
use Marketplace\Models\Package;
use Marketplace\Models\PackageRelease;
use Marketplace\Models\Setting;
use Marketplace\Models\User;
use Marketplace\Services\LegacyImporter;
use Marketplace\Services\Signing;
use Marketplace\Services\SigningKey;
use PDO;
use Tests\TestCase;
use ZipArchive;

/**
 * php artisan marketplace:import-legacy — copies the original catalog app's
 * SQLite data so issued keys and connected stores keep working.
 */
class LegacyImportTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/legacy-'.bin2hex(random_bytes(5));
        mkdir($this->dir.'/sources/module/acme/reviews', 0777, true);
        mkdir($this->dir.'/uploads', 0777, true);
        $this->secret = Signing::generateKeyPair()['secret'];
        $this->buildLegacy();
    }

    protected function tearDown(): void
    {
        $this->app['files']->deleteDirectory($this->dir);
        parent::tearDown();
    }

    private function buildLegacy(): void
    {
        $zip = new ZipArchive;
        $zip->open($this->dir.'/sources/module/acme/reviews/1.1.0.zip', ZipArchive::CREATE);
        $zip->addFromString('module.json', '{"name":"acme/reviews","version":"1.1.0"}');
        $zip->close();
        file_put_contents($this->dir.'/uploads/thumb.png', 'png-bytes');

        $db = new PDO('sqlite:'.$this->dir.'/catalog.sqlite');
        $db->exec('CREATE TABLE admins (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL)');
        $db->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE, password_hash TEXT NOT NULL, created_at TEXT NOT NULL)');
        $db->exec('CREATE TABLE settings (key TEXT PRIMARY KEY, value TEXT NOT NULL)');
        $db->exec('CREATE TABLE offers (extension TEXT PRIMARY KEY, tier TEXT NOT NULL, price TEXT NOT NULL)');
        $db->exec('CREATE TABLE orders (id INTEGER PRIMARY KEY, customer_id INTEGER NOT NULL, extension TEXT NOT NULL, product_name TEXT NOT NULL, amount TEXT NOT NULL, currency TEXT NOT NULL, stripe_session TEXT NOT NULL UNIQUE, status TEXT NOT NULL, license_key TEXT, created_at TEXT NOT NULL)');
        $db->exec('CREATE TABLE listings (extension TEXT PRIMARY KEY, data TEXT NOT NULL, updated_at TEXT NOT NULL)');
        $db->exec("CREATE TABLE categories (slug TEXT PRIMARY KEY, name TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', type TEXT NOT NULL DEFAULT 'all', position INTEGER NOT NULL DEFAULT 0, enabled INTEGER NOT NULL DEFAULT 1)");
        $db->exec("CREATE TABLE submissions (id INTEGER PRIMARY KEY, customer_id INTEGER NOT NULL, extension TEXT NOT NULL, data TEXT NOT NULL, status TEXT NOT NULL DEFAULT 'pending', review_note TEXT NOT NULL DEFAULT '', reviewed_by INTEGER, created_at TEXT NOT NULL, reviewed_at TEXT)");
        $db->exec("CREATE TABLE licenses (id INTEGER PRIMARY KEY, customer_id INTEGER NOT NULL, extension TEXT NOT NULL, product_name TEXT NOT NULL, product_slug TEXT NOT NULL, type TEXT NOT NULL, license_key TEXT NOT NULL UNIQUE, status TEXT NOT NULL DEFAULT 'active', created_at TEXT NOT NULL, UNIQUE(customer_id, extension))");
        $db->exec('CREATE TABLE package_releases (id INTEGER PRIMARY KEY, extension TEXT NOT NULL, version TEXT NOT NULL, path TEXT NOT NULL, sha256 TEXT NOT NULL, size INTEGER NOT NULL, platform_version TEXT NOT NULL, signed_metadata TEXT NOT NULL, signature TEXT NOT NULL, published_at TEXT NOT NULL, UNIQUE(extension, version))');
        $db->exec('CREATE TABLE license_activations (license_id INTEGER NOT NULL, instance_id TEXT NOT NULL, domain TEXT NOT NULL DEFAULT \'\', active INTEGER NOT NULL DEFAULT 1, activated_at TEXT NOT NULL, PRIMARY KEY(license_id, instance_id))');

        $hash = password_hash('Customer-pass-1', PASSWORD_BCRYPT);
        $db->exec("INSERT INTO admins VALUES (1, 'admin@example.test', '".password_hash('Admin-pass-1', PASSWORD_BCRYPT)."', '2026-10-01T00:00:00+00:00')");
        $db->prepare('INSERT INTO customers VALUES (5, ?, ?, ?, ?)')->execute(['Ana Buyer', 'ana@example.test', $hash, '2026-10-02T00:00:00+00:00']);
        $db->prepare('INSERT INTO settings VALUES (?, ?)')->execute(['license_signing_secret', $this->secret]);
        $db->prepare('INSERT INTO settings VALUES (?, ?)')->execute(['stripe_secret', 'sk_test_legacy']);
        $db->prepare('INSERT INTO settings VALUES (?, ?)')->execute(['currency', 'USD']);
        $db->exec("INSERT INTO offers VALUES ('acme/reviews', 'premium', '29.00')");
        $db->exec("INSERT INTO offers VALUES ('acme/only-offer', 'free', '0')");
        $db->prepare('INSERT INTO listings VALUES (?, ?, ?)')->execute(['acme/reviews', json_encode([
            'extension' => 'acme/reviews', 'slug' => 'acme-reviews', 'name' => 'Acme Reviews', 'summary' => 'Ratings', 'type' => 'module',
            'categories' => ['marketing'], 'features' => ['Stars'], 'featured' => true, 'demo_url' => 'https://demo.example.com',
            'thumbnail' => '/uploads/thumb.png', 'status' => 'published', 'repository_url' => '',
            'author' => 'Acme Labs', 'license' => 'GPL-3.0', 'version' => '1.1.0', 'platform_version' => '^1.2',
        ]), '2026-10-03T00:00:00+00:00']);
        $db->exec("INSERT INTO categories VALUES ('marketing', 'Marketing & sales', '', 'module', 4, 1)");
        $db->exec("INSERT INTO licenses VALUES (9, 5, 'acme/reviews', 'Acme Reviews', 'acme-reviews', 'paid', 'LEG-KEY-0001', 'active', '2026-10-04T00:00:00+00:00')");
        $db->exec("INSERT INTO license_activations VALUES (9, 'store-legacy', 'shop.example', 1, '2026-10-05T00:00:00+00:00')");
        $db->exec("INSERT INTO orders VALUES (3, 5, 'acme/reviews', 'Acme Reviews', '29.00', 'USD', 'cs_legacy_1', 'paid', 'LEG-KEY-0001', '2026-10-04T00:00:00+00:00')");
        $path = $this->dir.'/sources/module/acme/reviews/1.1.0.zip';
        $db->prepare('INSERT INTO package_releases VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)')->execute(['acme/reviews', '1.1.0', $path, hash_file('sha256', $path), filesize($path), '^1.0', '{}', 'x', '2026-10-04T00:00:00+00:00']);
    }

    private function import(): array
    {
        return app(LegacyImporter::class)->import($this->dir.'/catalog.sqlite');
    }

    public function test_accounts_keys_activations_orders_and_releases_are_copied()
    {
        $counts = $this->import();

        $this->assertSame(2, $counts['users']);
        $this->assertTrue(Auth::attempt(['email' => 'ana@example.test', 'password' => 'Customer-pass-1']));
        $this->assertTrue(User::query()->where('email', 'admin@example.test')->value('is_admin'));

        $package = Package::query()->where('identifier', 'acme/reviews')->firstOrFail();
        $this->assertSame('Acme Reviews', $package->name);
        $this->assertSame('29.00', $package->price);
        $this->assertSame(['Acme Labs', 'GPL-3.0', '1.1.0', '^1.2'], [$package->author, $package->license_name, $package->version, $package->platform_version]);
        $this->assertFalse($package->is_free);
        $this->assertTrue($package->featured);
        $this->assertSame(['marketing'], $package->categories->pluck('slug')->all());
        $this->assertNotNull($package->thumbnail_path);
        $this->assertTrue(Package::query()->where('identifier', 'acme/only-offer')->value('is_free'));

        $license = License::query()->where('key', 'LEG-KEY-0001')->firstOrFail();
        $this->assertSame('store-legacy', $license->activeActivation()?->instance_id);
        $this->assertSame('paid', Order::query()->where('stripe_session', 'cs_legacy_1')->value('status'));
        $this->assertSame($license->id, Order::query()->where('stripe_session', 'cs_legacy_1')->value('license_id'));

        $release = PackageRelease::query()->where('version', '1.1.0')->firstOrFail();
        $this->assertTrue(Signing::verify($release->signed_metadata, $release->signature, [app(SigningKey::class)->publicKey()]));
        $this->assertSame('acme/reviews', $release->signed_metadata['extension']);
    }

    public function test_the_signing_key_is_kept_so_stores_still_trust_answers()
    {
        $this->import();

        $this->assertSame(Signing::publicKeyFromSecret($this->secret), app(SigningKey::class)->publicKey());
        $this->assertSame('sk_test_legacy', Setting::get('stripe_secret'));
        $this->assertNotSame('sk_test_legacy', \DB::table('settings')->where('key', 'stripe_secret')->value('value'));

        // A store activated before the move checks its key against the new app.
        $this->postJson('/api/marketplace/v1/licenses/check', ['key' => 'LEG-KEY-0001', 'instance_id' => 'store-legacy'])
            ->assertOk()->assertJsonPath('token.activated', true);
    }

    public function test_importing_twice_adds_nothing()
    {
        $first = $this->import();
        $second = $this->import();

        $this->assertSame($first, $second);
        $this->assertSame(2, User::query()->count());
        $this->assertSame(1, License::query()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, PackageRelease::query()->count());
    }

    public function test_accounts_registered_here_before_the_import_are_not_merged_or_promoted()
    {
        $squatter = User::factory()->create(['email' => 'admin@example.test', 'password' => 'Squatter-pass-1']);
        $other = User::factory()->create(['email' => 'ana@example.test', 'password' => 'Squatter-pass-1']);

        $importer = app(LegacyImporter::class);
        $importer->import($this->dir.'/catalog.sqlite');

        $this->assertFalse($squatter->fresh()->is_admin);
        $this->assertSame(0, License::query()->where('user_id', $other->id)->count());
        $this->assertSame(0, Order::query()->where('user_id', $other->id)->count());
        $this->assertNotEmpty(array_filter($importer->warnings, fn ($w) => str_contains($w, 'ana@example.test')));
    }

    public function test_values_longer_than_the_new_columns_are_shortened_or_skipped()
    {
        $db = new PDO('sqlite:'.$this->dir.'/catalog.sqlite');
        $db->prepare('INSERT INTO categories VALUES (?, ?, ?, ?, ?, ?)')->execute([str_repeat('s', 80), 'Long', str_repeat('d', 500), 'all', 9, 1]);
        $db->prepare('INSERT INTO listings VALUES (?, ?, ?)')->execute(['acme/long', json_encode(['name' => 'Long', 'type' => 'module', 'status' => 'published',
            'demo_url' => 'https://demo.example.com/'.str_repeat('a', 600), 'repository_url' => 'https://git.example.com/'.str_repeat('r', 600)]), '2026-10-03T00:00:00+00:00']);
        $db->prepare('INSERT INTO customers VALUES (7, ?, ?, ?, ?)')->execute(['Long', str_repeat('e', 200).'@example.test', 'x', '2026-10-02T00:00:00+00:00']);

        $this->import();

        $category = \Marketplace\Models\Category::query()->where('name', 'Long')->firstOrFail();
        $this->assertLessThanOrEqual(64, strlen($category->slug));
        $this->assertLessThanOrEqual(255, strlen($category->description));
        $long = Package::query()->where('identifier', 'acme/long')->firstOrFail();
        $this->assertNull($long->demo_url);
        $this->assertNull($long->repository_url);
        $this->assertSame(0, User::query()->where('email', 'like', 'eeee%')->count());
    }

    public function test_running_again_keeps_settings_changed_here()
    {
        $this->import();
        Setting::put('stripe_secret', 'sk_test_rotated', secret: true);
        Setting::put('currency', 'EUR');

        $this->import();

        $this->assertSame('sk_test_rotated', Setting::get('stripe_secret'));
        $this->assertSame('EUR', Setting::get('currency'));
    }

    public function test_the_logo_file_is_copied_and_served()
    {
        file_put_contents($this->dir.'/uploads/logo.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=='));
        (new PDO('sqlite:'.$this->dir.'/catalog.sqlite'))->prepare('INSERT INTO settings VALUES (?, ?)')->execute(['logo', '/uploads/logo.png']);

        $this->import();

        $this->assertSame('branding/logo.png', Setting::get('logo'));
        $this->get('/branding/logo')->assertOk();
    }

    public function test_the_import_command_closes_the_installer_when_it_brings_an_admin()
    {
        unlink(config('marketplace.installed_file'));

        $this->artisan('marketplace:import-legacy', ['path' => $this->dir.'/catalog.sqlite'])->assertSuccessful();

        $this->assertFileExists(config('marketplace.installed_file'));
    }

    public function test_install_creates_the_first_admin_and_refuses_a_second_time()
    {
        $this->artisan('marketplace:install', ['--name' => 'Owner', '--email' => 'owner@example.test', '--password' => 'Long-enough-pass-1'])->assertSuccessful();

        $this->assertTrue(User::query()->where('email', 'owner@example.test')->value('is_admin'));
        $this->assertNotEmpty(app(SigningKey::class)->publicKey());

        $this->artisan('marketplace:install', ['--name' => 'Other', '--email' => 'other@example.test', '--password' => 'Long-enough-pass-1'])->assertFailed();
        $this->assertSame(1, User::query()->where('is_admin', true)->count());
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Marketplace\Models\Package;
use Tests\TestCase;

/** `marketplace:publish` zips a package from the source tree and publishes it as a signed release. */
class PublishCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir().'/mk-source-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->root.'/modules/acme/reviews/src');
        File::put($this->root.'/modules/acme/reviews/module.json', json_encode(['name' => 'acme/reviews', 'version' => '1.2.0', 'title' => 'Acme Reviews', 'description' => 'Star ratings']));
        File::put($this->root.'/modules/acme/reviews/src/Provider.php', '<?php');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_publishes_a_new_package_from_source()
    {
        $this->artisan('marketplace:publish', ['identifier' => 'acme/reviews', '--root' => $this->root])->assertSuccessful();

        $package = Package::query()->where('identifier', 'acme/reviews')->firstOrFail();
        $this->assertSame('module', $package->type);
        $this->assertSame('published', $package->status);
        $this->assertSame('Acme Reviews', $package->name);
        $this->assertSame('1.2.0', $package->latestRelease()?->version);
    }

    public function test_publishing_again_adds_the_new_version_and_keeps_the_listing()
    {
        $this->artisan('marketplace:publish', ['identifier' => 'acme/reviews', '--root' => $this->root])->assertSuccessful();
        Package::query()->where('identifier', 'acme/reviews')->update(['name' => 'Renamed by admin']);
        File::put($this->root.'/modules/acme/reviews/module.json', json_encode(['name' => 'acme/reviews', 'version' => '1.3.0']));

        $this->artisan('marketplace:publish', ['identifier' => 'acme/reviews', '--root' => $this->root])->assertSuccessful();

        $package = Package::query()->where('identifier', 'acme/reviews')->firstOrFail();
        $this->assertSame('Renamed by admin', $package->name);
        $this->assertSame('1.3.0', $package->latestRelease()?->version);
        $this->assertSame(2, $package->releases()->count());
    }

    public function test_a_malformed_manifest_version_fails()
    {
        File::put($this->root.'/modules/acme/reviews/module.json', json_encode(['name' => 'acme/reviews', 'version' => '1.0.0/../evil']));

        $this->artisan('marketplace:publish', ['identifier' => 'acme/reviews', '--root' => $this->root])->assertFailed();
        $this->assertSame(0, Package::query()->count());
    }

    public function test_unknown_packages_fail()
    {
        $this->artisan('marketplace:publish', ['identifier' => 'acme/missing', '--root' => $this->root])->assertFailed();
        $this->artisan('marketplace:publish', ['identifier' => '../etc', '--root' => $this->root])->assertFailed();
    }
}

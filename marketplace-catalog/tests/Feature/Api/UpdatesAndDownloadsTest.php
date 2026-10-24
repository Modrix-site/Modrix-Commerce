<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Marketplace\Models\License;
use Marketplace\Models\Package;
use Marketplace\Models\User;
use Marketplace\Services\Licensing;
use Marketplace\Services\Releases;
use Marketplace\Services\Signing;
use Marketplace\Services\SigningKey;
use Tests\TestCase;
use ZipArchive;

/**
 * POST updates / downloads and the one-time download link, as the store
 * platform's PackageInstaller uses them.
 */
class UpdatesAndDownloadsTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/marketplace/v1/';

    private Package $package;

    private License $license;

    protected function setUp(): void
    {
        parent::setUp();
        $this->package = Package::query()->create(['identifier' => 'acme/reviews', 'type' => 'module', 'slug' => 'acme-reviews', 'name' => 'Reviews', 'status' => 'published']);
        $this->license = app(Licensing::class)->issue(User::factory()->create(), $this->package, 'free');
        $this->postJson(self::API.'licenses/activate', ['key' => $this->license->key, 'instance_id' => 'store-a'])->assertOk();
    }

    private function zip(string $version): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pkg').'.zip';
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('module.json', json_encode(['name' => 'acme/reviews', 'version' => $version]));
        $zip->addFromString('src/Provider.php', '<?php // '.$version);
        $zip->close();

        return $path;
    }

    private function publish(string $version, string $platform = '^1.0', ?Package $package = null)
    {
        return app(Releases::class)->publish($package ?? $this->package, $this->zip($version), $version, $platform);
    }

    private function update(?string $installed, string $platform = '1.2.0'): array
    {
        return $this->postJson(self::API.'updates', ['key' => $this->license->key, 'instance_id' => 'store-a', 'installed_version' => $installed, 'platform_version' => $platform])->assertOk()->json();
    }

    public function test_updates_offer_the_newest_compatible_signed_release()
    {
        $this->publish('1.0.0');
        $newest = $this->publish('1.4.0');
        $this->publish('2.0.0', '^2.0');

        $body = $this->update('1.0.0');

        $this->assertTrue($body['available']);
        $this->assertSame($newest->id, $body['release']['id']);
        $this->assertSame('1.4.0', $body['release']['version']);
        $metadata = $body['release']['verification']['metadata'];
        $this->assertTrue(Signing::verify($metadata, $body['release']['verification']['signature'], [app(SigningKey::class)->publicKey()]));
        $this->assertSame('acme/reviews', $metadata['extension']);
        $this->assertSame('module', $metadata['type']);
        $this->assertSame('acme-reviews', $metadata['product']);
        $this->assertSame($newest->size, $metadata['size']);
        $this->assertIsInt($metadata['size']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $metadata['sha256']);
        $this->assertSame('^1.0', $metadata['platform_version']);
    }

    public function test_nothing_newer_means_no_update()
    {
        $this->publish('1.4.0');

        $body = $this->update('1.4.0');

        $this->assertFalse($body['available']);
        $this->assertNull($body['release']);
        $this->assertIsString($body['reason']);
    }

    public function test_a_download_link_streams_the_signed_bytes_once()
    {
        $release = $this->publish('1.4.0');

        $body = $this->postJson(self::API.'downloads', ['key' => $this->license->key, 'instance_id' => 'store-a', 'release_id' => $release->id])->assertOk()->json();

        $this->assertStringStartsWith(config('app.url').'/', $body['url']);
        $this->assertSame($release->id, $body['release']['id']);
        $path = parse_url($body['url'], PHP_URL_PATH);
        $response = $this->get($path)->assertOk();
        $this->assertSame($release->size, strlen($response->streamedContent()));
        $this->assertSame($release->sha256, hash('sha256', $response->streamedContent()));

        $this->get($path)->assertStatus(410);
    }

    public function test_expired_links_are_refused()
    {
        $release = $this->publish('1.4.0');
        $url = $this->postJson(self::API.'downloads', ['key' => $this->license->key, 'instance_id' => 'store-a', 'release_id' => $release->id])->json('url');

        Carbon::setTestNow(now()->addMinutes(11));

        $this->get(parse_url($url, PHP_URL_PATH))->assertStatus(410);
    }

    public function test_downloads_need_this_package_and_an_activated_store()
    {
        $other = Package::query()->create(['identifier' => 'acme/other', 'type' => 'module', 'slug' => 'acme-other', 'name' => 'Other', 'status' => 'published']);
        $foreign = $this->publish('1.0.0', '^1.0', $other);
        $mine = $this->publish('1.0.0');

        $this->postJson(self::API.'downloads', ['key' => $this->license->key, 'instance_id' => 'store-a', 'release_id' => $foreign->id])
            ->assertStatus(404)->assertJson(['error' => 'release_not_found']);
        $this->postJson(self::API.'downloads', ['key' => $this->license->key, 'instance_id' => 'store-b', 'release_id' => $mine->id])
            ->assertStatus(403)->assertJson(['error' => 'not_activated']);
        $this->get('/api/marketplace/v1/download/not-a-real-token')->assertStatus(410);
    }
}

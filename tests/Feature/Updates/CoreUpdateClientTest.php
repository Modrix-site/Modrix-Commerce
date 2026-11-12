<?php

namespace Tests\Feature\Updates;

use App\Core\Marketplace\TrustedKeys;
use App\Core\Settings\SecretSettings;
use App\Core\Settings\SettingsRepository;
use App\Core\Updates\CoreUpdateClient;
use App\Core\Updates\CoreUpdateError;
use EcoModular\CloudConnector\CloudClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class CoreUpdateClientTest extends TestCase
{
    use ModuleFixtures, RefreshDatabase;

    private const TOKEN = 'ct_0123456789abcdef0123456789abcdef0123456789abcdef0123456789abc';

    private const BASE = 'https://cloud.test';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ecomodular.marketplace.url' => self::BASE]);
    }

    private function connect(): void
    {
        app(SecretSettings::class)->put('cloud.connection_token', self::TOKEN);
    }

    private function client(): CoreUpdateClient
    {
        return app(CoreUpdateClient::class);
    }

    public function test_lists_releases(): void
    {
        Http::fake([self::BASE.'/api/marketplace/v1/core/releases*' => Http::response(['releases' => [['id' => 3, 'version' => '1.1.0']]])]);

        $releases = $this->client()->releases('1.0.0', '8.4.1');

        $this->assertSame([['id' => 3, 'version' => '1.1.0']], $releases);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'current=1.0.0') && str_contains($r->url(), 'php=8.4.1'));
    }

    public function test_download_request_without_connection_is_refused(): void
    {
        Http::fake();

        try {
            $this->client()->requestDownload(3);
            $this->fail('Expected CoreUpdateError');
        } catch (CoreUpdateError $e) {
            $this->assertTrue($e->notConnected);
            $this->assertStringContainsString('Modrix Cloud', $e->getMessage());
        }

        Http::assertNothingSent();
    }

    public function test_bearer_token_is_sent(): void
    {
        $this->connect();
        Http::fake([self::BASE.'/api/marketplace/v1/core/downloads' => Http::response(['url' => self::BASE.'/d/x', 'expires_at' => 'soon', 'release' => []])]);

        $result = $this->client()->requestDownload(3);

        $this->assertSame(self::BASE.'/d/x', $result['url']);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'Bearer '.self::TOKEN)
            && $r['release_id'] === 3
            && $r['instance_id'] === app(SettingsRepository::class)->get('marketplace.instance_id'));
    }

    public function test_not_connected_answer_maps_to_not_connected_error(): void
    {
        $this->connect();
        Http::fake(['*' => Http::response(['error' => 'not_connected', 'message' => 'Gone'], 401)]);

        try {
            $this->client()->requestDownload(3);
            $this->fail('Expected CoreUpdateError');
        } catch (CoreUpdateError $e) {
            $this->assertTrue($e->notConnected);
        }
    }

    public function test_download_refuses_foreign_host(): void
    {
        Http::fake();
        $target = tempnam(sys_get_temp_dir(), 'cu');

        try {
            $this->client()->download('https://evil.test/file.zip', $target, 10);
            $this->fail('Expected CoreUpdateError');
        } catch (CoreUpdateError) {
            $this->assertTrue(true);
        } finally {
            @unlink($target);
        }

        Http::assertNothingSent();
    }

    public function test_download_refuses_oversize_body(): void
    {
        Http::fake(['*' => Http::response(str_repeat('a', 50))]);
        $target = tempnam(sys_get_temp_dir(), 'cu');

        try {
            $this->client()->download(self::BASE.'/d/x', $target, 10);
            $this->fail('Expected CoreUpdateError');
        } catch (CoreUpdateError) {
            $this->assertFileDoesNotExist($target);
        } finally {
            @unlink($target);
        }
    }

    public function test_download_writes_exact_size(): void
    {
        Http::fake(['*' => Http::response('0123456789')]);
        $target = tempnam(sys_get_temp_dir(), 'cu');

        $this->client()->download(self::BASE.'/d/x', $target, 10);

        $this->assertSame('0123456789', file_get_contents($target));
        @unlink($target);
    }

    public function test_core_and_connector_share_trusted_keys(): void
    {
        $this->installPlatform();
        $this->setUpModules();
        $this->enableBundledModule('modrixcommerce/cloud-connector');
        config(['ecomodular.marketplace.trusted_keys' => ['extra-key', TrustedKeys::OFFICIAL]]);

        $this->assertSame(TrustedKeys::OFFICIAL, CloudClient::TRUSTED_KEY);
        $this->assertSame([TrustedKeys::OFFICIAL, 'extra-key'], TrustedKeys::all());
        $this->assertSame(TrustedKeys::all(), app(CloudClient::class)->trustedKeys());
    }
}

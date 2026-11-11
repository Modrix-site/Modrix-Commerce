<?php

namespace Tests\Feature\Updates;

use App\Core\Operations\HealthCheck;
use App\Core\Settings\SettingsRepository;
use App\Core\Updates\CoreReleaseVerifier;
use App\Core\Updates\CoreUpdateCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\CoreReleaseFixtures;
use Tests\TestCase;

class CoreUpdateCheckTest extends TestCase
{
    use CoreReleaseFixtures, RefreshDatabase;

    private const BASE = 'https://cloud.test';

    private string $hash;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ecomodular.marketplace.url' => self::BASE]);
        $this->useTestSigningKey();
        $this->hash = str_repeat('a', 64);
    }

    /** @param list<array<string, mixed>> $items */
    private function fakeReleases(array $items): void
    {
        Http::fake([self::BASE.'/api/marketplace/v1/core/releases*' => Http::response(['releases' => $items])]);
    }

    private function settings(): SettingsRepository
    {
        return app(SettingsRepository::class);
    }

    public function test_check_stores_newest_verified_release(): void
    {
        $this->fakeReleases([
            $this->signedItem('1.1.0', $this->hash, 1000),
            $this->signedItem('1.2.0', $this->hash, 2000),
        ]);

        $result = app(CoreUpdateCheck::class)->run();

        $this->assertSame('1.2.0', $result['version']);
        $this->assertSame('Release notes for 1.2.0', $result['notes']);
        $this->assertSame('1.2.0', $this->settings()->get('core_update.available')['version']);
        $this->assertNotNull($this->settings()->get('core_update.checked_at'));
        $this->assertNull($this->settings()->get('core_update.last_error'));
        $this->assertSame('1.2.0', app(CoreUpdateCheck::class)->available()['version']);
    }

    public function test_check_keeps_the_raw_signed_item_for_the_installer(): void
    {
        $item = $this->signedItem('1.1.0', $this->hash, 1000);
        $this->fakeReleases([$item]);

        app(CoreUpdateCheck::class)->run();

        // Key order may differ (MySQL JSON); signatures use sorted keys.
        $this->assertEquals($item, app(CoreUpdateCheck::class)->item());
        $this->assertNotNull(app(CoreReleaseVerifier::class)->verify(app(CoreUpdateCheck::class)->item()));
    }

    public function test_check_ignores_bad_signature(): void
    {
        $this->fakeReleases([$this->signedItem('1.1.0', $this->hash, 1000, tamper: true)]);

        $this->assertNull(app(CoreUpdateCheck::class)->run());
        $this->assertNull($this->settings()->get('core_update.available'));
    }

    public function test_check_ignores_non_core_kind(): void
    {
        $this->fakeReleases([$this->signedItem('1.1.0', $this->hash, 1000, override: ['kind' => 'extension'])]);

        $this->assertNull(app(CoreUpdateCheck::class)->run());
    }

    public function test_check_ignores_older_version(): void
    {
        $this->fakeReleases([$this->signedItem('0.9.0', $this->hash, 1000), $this->signedItem('1.0.0-rc.1', $this->hash, 1000)]);

        $this->assertNull(app(CoreUpdateCheck::class)->run());
    }

    public function test_check_ignores_too_high_min_php(): void
    {
        $this->fakeReleases([$this->signedItem('1.1.0', $this->hash, 1000, '99.0')]);

        $this->assertNull(app(CoreUpdateCheck::class)->run());
    }

    public function test_check_ignores_mismatched_release_id(): void
    {
        $item = $this->signedItem('1.1.0', $this->hash, 1000);
        $item['id'] = $item['id'] + 1;
        $this->fakeReleases([$item]);

        $this->assertNull(app(CoreUpdateCheck::class)->run());
    }

    public function test_check_records_error_when_cloud_is_down(): void
    {
        $this->settings()->set('core_update.available', ['version' => '1.1.0']);
        Http::fake([self::BASE.'/*' => Http::response('boom', 500)]);

        $this->assertNull(app(CoreUpdateCheck::class)->run());

        $this->assertNotEmpty($this->settings()->get('core_update.last_error'));
        $this->assertSame('1.1.0', $this->settings()->get('core_update.available')['version']);
    }

    public function test_command_runs_the_check(): void
    {
        $this->fakeReleases([$this->signedItem('1.1.0', $this->hash, 1000)]);

        $this->artisan('ecomodular:core-check')->assertExitCode(0);

        $this->assertSame('1.1.0', $this->settings()->get('core_update.available')['version']);
    }

    public function test_health_ok_when_up_to_date(): void
    {
        $check = collect(app(HealthCheck::class)->run())->firstWhere('key', 'core_updates');

        $this->assertSame('Core updates', $check['label']);
        $this->assertSame(HealthCheck::OK, $check['status']);
        $this->assertStringStartsWith('Up to date', $check['detail']);
    }

    public function test_health_warns_when_update_is_available(): void
    {
        $this->settings()->set('core_update.available', ['version' => '1.1.0']);

        $check = collect(app(HealthCheck::class)->run())->firstWhere('key', 'core_updates');

        $this->assertSame(HealthCheck::WARNING, $check['status']);
        $this->assertSame('Modrix Commerce 1.1.0 is available', $check['detail']);
    }
}

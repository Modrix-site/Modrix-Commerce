<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Marketplace\Models\License;
use Marketplace\Models\LicenseActivation;
use Marketplace\Models\Package;
use Marketplace\Models\User;
use Marketplace\Services\Licensing;
use Marketplace\Services\Signing;
use Marketplace\Services\SigningKey;
use Tests\TestCase;

/**
 * POST /api/marketplace/v1/licenses/{activate,check,deactivate}, as the store
 * platform's MarketplaceClient calls them.
 */
class LicenseApiTest extends TestCase
{
    use RefreshDatabase;

    private const API = '/api/marketplace/v1/';

    private function license(): License
    {
        $package = Package::query()->create(['identifier' => 'acme/reviews', 'type' => 'module', 'slug' => 'acme-reviews', 'name' => 'Reviews', 'status' => 'published']);

        return app(Licensing::class)->issue(User::factory()->create(), $package, 'free');
    }

    private function verified(array $body): bool
    {
        return Signing::verify($body['token'], $body['signature'], [app(SigningKey::class)->publicKey()]);
    }

    public function test_activating_returns_a_signed_token_for_that_store()
    {
        $license = $this->license();

        $body = $this->postJson(self::API.'licenses/activate', ['key' => $license->key, 'instance_id' => 'store-a', 'domain' => 'shop-a.example', 'platform_version' => '1.0.0'])
            ->assertOk()->json();

        $this->assertTrue($this->verified($body));
        $this->assertTrue($body['token']['activated']);
        $this->assertSame('store-a', $body['token']['instance_id']);
        $this->assertSame('shop-a.example', LicenseActivation::query()->firstOrFail()->domain);
    }

    public function test_activating_the_same_store_twice_is_idempotent()
    {
        $license = $this->license();

        $this->postJson(self::API.'licenses/activate', ['key' => $license->key, 'instance_id' => 'store-a'])->assertOk();
        $this->postJson(self::API.'licenses/activate', ['key' => $license->key, 'instance_id' => 'store-a'])->assertOk();

        $this->assertSame(1, LicenseActivation::query()->count());
    }

    public function test_a_key_active_on_another_store_is_refused()
    {
        $license = $this->license();
        $this->postJson(self::API.'licenses/activate', ['key' => $license->key, 'instance_id' => 'store-a'])->assertOk();

        $this->postJson(self::API.'licenses/activate', ['key' => $license->key, 'instance_id' => 'store-b'])
            ->assertStatus(409)->assertJson(['error' => 'license_in_use']);

        $this->assertTrue($license->activeActivation()?->instance_id === 'store-a');
    }

    public function test_after_deactivating_the_key_moves_to_another_store()
    {
        $license = $this->license();
        $this->postJson(self::API.'licenses/activate', ['key' => $license->key, 'instance_id' => 'store-a'])->assertOk();

        $this->postJson(self::API.'licenses/deactivate', ['key' => $license->key, 'instance_id' => 'store-a'])->assertOk()->assertJson(['ok' => true]);
        $this->postJson(self::API.'licenses/activate', ['key' => $license->key, 'instance_id' => 'store-b'])->assertOk();

        $this->assertSame('store-b', $license->activeActivation()?->instance_id);
    }

    public function test_check_reports_whether_this_store_is_activated()
    {
        $license = $this->license();
        $this->postJson(self::API.'licenses/activate', ['key' => $license->key, 'instance_id' => 'store-a'])->assertOk();

        $other = $this->postJson(self::API.'licenses/check', ['key' => $license->key, 'instance_id' => 'store-b'])->assertOk()->json();
        $same = $this->postJson(self::API.'licenses/check', ['key' => $license->key, 'instance_id' => 'store-a'])->assertOk()->json();

        $this->assertTrue($this->verified($other));
        $this->assertFalse($other['token']['activated']);
        $this->assertTrue($same['token']['activated']);
    }

    public function test_revoked_unknown_and_invalid_requests_are_refused()
    {
        $license = $this->license();
        $license->update(['status' => 'revoked']);

        $this->postJson(self::API.'licenses/activate', ['key' => $license->key, 'instance_id' => 'store-a'])
            ->assertStatus(403)->assertJson(['error' => 'license_inactive']);
        $this->postJson(self::API.'licenses/activate', ['key' => 'MRX-NOPE', 'instance_id' => 'store-a'])
            ->assertStatus(404)->assertJson(['error' => 'invalid_key']);
        $this->postJson(self::API.'licenses/activate', ['key' => $license->key])
            ->assertStatus(422)->assertJson(['error' => 'validation_failed'])->assertJsonStructure(['message']);
    }
}

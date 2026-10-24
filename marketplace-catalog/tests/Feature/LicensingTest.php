<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Marketplace\Models\Package;
use Marketplace\Models\User;
use Marketplace\Services\Licensing;
use Marketplace\Services\Signing;
use Marketplace\Services\SigningKey;
use Tests\TestCase;

class LicensingTest extends TestCase
{
    use RefreshDatabase;

    private function package(): Package
    {
        return Package::query()->create(['identifier' => 'acme/reviews', 'type' => 'module', 'slug' => 'acme-reviews', 'name' => 'Reviews', 'status' => 'published']);
    }

    public function test_issuing_twice_returns_the_same_key_in_the_expected_format()
    {
        $user = User::factory()->create();
        $package = $this->package();
        $licensing = app(Licensing::class);

        $first = $licensing->issue($user, $package, 'free');
        $second = $licensing->issue($user, $package, 'free');

        $this->assertSame($first->id, $second->id);
        $this->assertMatchesRegularExpression('/^MRX-([A-Z2-7]{5}-){3}[A-Z2-7]{5}$/', $first->key);
        $this->assertSame('active', $first->status);
    }

    public function test_the_signing_key_is_created_once_and_kept()
    {
        $public = app(SigningKey::class)->publicKey();

        $this->assertSame($public, app(SigningKey::class)->publicKey());
        $this->assertSame(Signing::keyId(base64_decode($public)), app(SigningKey::class)->keyId());
    }

    public function test_tokens_are_signed_and_valid_for_thirty_days()
    {
        Carbon::setTestNow('2026-10-24 12:00:00');
        $license = app(Licensing::class)->issue(User::factory()->create(), $this->package(), 'free');

        ['token' => $token, 'signature' => $signature] = app(Licensing::class)->token($license, 'store-1');

        $this->assertTrue(Signing::verify($token, $signature, [app(SigningKey::class)->publicKey()]));
        $this->assertSame(1, $token['schema']);
        $this->assertSame($license->id, $token['license_id']);
        $this->assertSame('acme-reviews', $token['product']);
        $this->assertSame('acme/reviews', $token['extension']);
        $this->assertSame('free', $token['type']);
        $this->assertSame('active', $token['status']);
        $this->assertFalse($token['activated']);
        $this->assertSame('store-1', $token['instance_id']);
        $this->assertNull($token['updates_until']);
        $this->assertSame([], $token['advisories']);
        $this->assertSame(app(SigningKey::class)->keyId(), $token['key_id']);
        $this->assertSame(Carbon::parse($token['issued_at'])->addDays(30)->toIso8601String(), Carbon::parse($token['valid_until'])->toIso8601String());
    }
}

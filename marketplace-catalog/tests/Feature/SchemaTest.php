<?php

namespace Tests\Feature;

use Database\Seeders\CategorySeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Marketplace\Models\Category;
use Marketplace\Models\License;
use Marketplace\Models\Package;
use Marketplace\Models\Setting;
use Marketplace\Models\User;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    private function package(): Package
    {
        return Package::query()->create([
            'identifier' => 'acme/reviews', 'type' => 'module', 'slug' => 'acme-reviews',
            'name' => 'Reviews', 'price' => '0', 'currency' => 'USD', 'is_free' => true, 'status' => 'published',
        ]);
    }

    public function test_a_license_belongs_to_one_user_and_package()
    {
        $user = User::factory()->create();
        $package = $this->package();
        License::query()->create(['user_id' => $user->id, 'package_id' => $package->id, 'key' => 'MRX-AAAAA-AAAAA-AAAAA-AAAAA', 'type' => 'free', 'status' => 'active']);

        $this->expectException(QueryException::class);
        License::query()->create(['user_id' => $user->id, 'package_id' => $package->id, 'key' => 'MRX-BBBBB-BBBBB-BBBBB-BBBBB', 'type' => 'free', 'status' => 'active']);
    }

    public function test_settings_store_secrets_encrypted()
    {
        Setting::put('stripe_secret', 'sk_test_x', secret: true);
        Setting::put('name', 'Modrix Marketplace');

        $this->assertNotSame('sk_test_x', DB::table('settings')->where('key', 'stripe_secret')->value('value'));
        $this->assertSame('sk_test_x', Setting::get('stripe_secret'));
        $this->assertSame('Modrix Marketplace', Setting::get('name'));
        $this->assertSame('fallback', Setting::get('missing', 'fallback'));
    }

    public function test_categories_are_seeded()
    {
        $this->seed(CategorySeeder::class);
        $this->seed(CategorySeeder::class);

        $this->assertSame(6, Category::query()->count());
        $this->assertSame(['electronics', 'fashion', 'home-living', 'payments', 'marketing', 'operations'], Category::query()->orderBy('position')->pluck('slug')->all());
    }

    public function test_package_relations_and_latest_release()
    {
        $package = $this->package();
        $package->releases()->create(['version' => '1.0.0', 'path' => 'a.zip', 'sha256' => str_repeat('a', 64), 'size' => 10, 'platform_version' => '^1.0', 'signed_metadata' => ['v' => 1], 'signature' => 'sig', 'published_at' => now()->subDay()]);
        $package->releases()->create(['version' => '1.2.0', 'path' => 'b.zip', 'sha256' => str_repeat('b', 64), 'size' => 10, 'platform_version' => '^1.0', 'signed_metadata' => ['v' => 1], 'signature' => 'sig', 'published_at' => now()]);
        $package->releases()->create(['version' => '1.10.0', 'path' => 'c.zip', 'sha256' => str_repeat('c', 64), 'size' => 10, 'platform_version' => '^1.0', 'signed_metadata' => ['v' => 1], 'signature' => 'sig', 'published_at' => now()->subWeek()]);
        $package->categories()->attach(Category::query()->create(['slug' => 'payments', 'name' => 'Payments', 'type' => 'module'])->id);

        $this->assertSame('1.10.0', $package->latestRelease()?->version);
        $this->assertSame(['payments'], $package->categories->pluck('slug')->all());
    }
}

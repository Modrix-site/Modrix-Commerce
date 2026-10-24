<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Marketplace\Models\Package;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_lists_only_published_packages()
    {
        Package::query()->create(['identifier' => 'acme/free', 'type' => 'module', 'slug' => 'acme-free', 'name' => 'Free one', 'status' => 'published', 'is_free' => true, 'price' => '0', 'demo_url' => 'https://demo.example.com']);
        Package::query()->create(['identifier' => 'acme/paid', 'type' => 'theme', 'slug' => 'acme-paid', 'name' => 'Paid one', 'status' => 'published', 'is_free' => false, 'price' => '49.00']);
        Package::query()->create(['identifier' => 'acme/draft', 'type' => 'module', 'slug' => 'acme-draft', 'name' => 'Draft', 'status' => 'draft']);
        Package::query()->create(['identifier' => 'acme/hidden', 'type' => 'module', 'slug' => 'acme-hidden', 'name' => 'Hidden', 'status' => 'hidden']);

        $products = $this->getJson('/api/marketplace/v1/catalog')->assertOk()->json('products');

        $this->assertSame(['acme/free', 'acme/paid'], array_column($products, 'identifier'));
        $free = $products[0];
        $this->assertSame('module', $free['type']);
        $this->assertSame('Free one', $free['name']);
        $this->assertSame('acme-free', $free['slug']);
        $this->assertTrue($free['is_free']);
        $this->assertSame('https://demo.example.com', $free['demo_url']);
        $this->assertSame(url('/download/acme-free'), $free['download_url']);
        $this->assertNull($products[1]['download_url']);
        $this->assertSame('49.00', $products[1]['price']);
        $this->assertArrayHasKey('latest_version', $free);
        $this->assertArrayHasKey('thumbnail_url', $free);
        $this->assertArrayHasKey('currency', $free);
    }
}

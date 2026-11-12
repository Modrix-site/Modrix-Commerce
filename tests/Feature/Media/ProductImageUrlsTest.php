<?php

namespace Tests\Feature\Media;

use App\Core\Extensions\Manifest;
use App\Core\Media\ProductImageUrls;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUrlsTest extends TestCase
{
    private function image(): ProductImage
    {
        Storage::fake('public');

        return new ProductImage(['disk' => 'public', 'path' => 'products/1/a.jpg']);
    }

    public function test_url_falls_back_to_disk_url_without_resolvers(): void
    {
        $image = $this->image();

        $this->assertSame(Storage::disk('public')->url('products/1/a.jpg'), $image->url());
    }

    public function test_registered_resolver_overrides_url(): void
    {
        $image = $this->image();
        app(ProductImageUrls::class)->register('acme/x', fn (ProductImage $i) => 'https://cdn.test/x.webp');

        $this->assertSame('https://cdn.test/x.webp', $image->url());
    }

    public function test_null_resolver_falls_through_and_forget_removes(): void
    {
        $image = $this->image();
        $urls = app(ProductImageUrls::class);

        $urls->register('acme/x', fn () => null);
        $this->assertSame(Storage::disk('public')->url('products/1/a.jpg'), $image->url());

        $urls->register('acme/x', fn () => 'https://cdn.test/x.webp');
        $this->assertSame('https://cdn.test/x.webp', $image->url());
        $urls->forget('acme/x');
        $this->assertSame(Storage::disk('public')->url('products/1/a.jpg'), $image->url());
    }

    public function test_manifest_accepts_catalog_image_urls_capability(): void
    {
        $this->assertArrayHasKey('catalog.image_urls', Manifest::CAPABILITIES);
    }
}

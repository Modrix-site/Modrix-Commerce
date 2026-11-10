<?php

namespace Tests\Feature;

use App\Support\PageEntry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class PageEntryTest extends TestCase
{
    public function test_production_pages_bundled_into_the_app_use_the_app_entry(): void
    {
        $original = public_path();
        $public = storage_path('framework/testing/page-entry-'.Str::random(10));
        File::ensureDirectoryExists($public.'/build');
        File::put($public.'/build/manifest.json', json_encode([
            'resources/js/app.tsx' => ['file' => 'assets/app.js'],
            'resources/js/pages/storefront/home.tsx' => ['file' => 'assets/home.js'],
        ]));
        $this->app->usePublicPath($public);
        try {
            $this->assertSame('resources/js/app.tsx', PageEntry::for('storefront/cart'));
            $this->assertSame('resources/js/app.tsx', PageEntry::for('storefront/product'));
            $this->assertSame('resources/js/pages/storefront/home.tsx', PageEntry::for('storefront/home'));
        } finally {
            $this->app->usePublicPath($original);
            File::deleteDirectory($public);
        }
    }
}

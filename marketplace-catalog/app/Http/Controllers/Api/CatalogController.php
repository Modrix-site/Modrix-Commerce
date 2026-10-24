<?php

namespace Marketplace\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Marketplace\Models\Package;
use Marketplace\Services\Catalog;

/**
 * GET /api/marketplace/v1/catalog — published packages for the store's
 * Admin → Marketplace page.
 */
class CatalogController
{
    public function __invoke(): JsonResponse
    {
        $products = Package::query()
            ->where('status', 'published')
            ->with('releases')
            ->orderByDesc('featured')
            ->orderBy('name')
            ->get()
            ->map(fn (Package $p) => self::row($p))
            ->values();

        return response()->json(['products' => $products]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function row(Package $package): array
    {
        return [
            'identifier' => $package->identifier,
            'type' => $package->type,
            'name' => $package->name,
            'slug' => $package->slug,
            'summary' => $package->summary,
            'price' => $package->price,
            'currency' => app(Catalog::class)->currency(),
            'is_free' => $package->is_free,
            'featured' => $package->featured,
            'thumbnail_url' => $package->thumbnail_path ? url('/media/'.$package->slug) : null,
            'demo_url' => $package->demo_url,
            'repository_url' => $package->repository_url,
            'url' => url('/item/'.$package->slug),
            'latest_version' => $package->latestRelease()?->version,
            // Free packages download directly; paid ones through a license.
            'download_url' => $package->is_free ? url('/download/'.$package->slug) : null,
        ];
    }
}

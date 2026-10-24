<?php

namespace Marketplace\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Marketplace\Models\Category;
use Marketplace\Models\Package;
use Marketplace\Models\Setting;
use Marketplace\Services\Catalog;
use Marketplace\Services\Releases;
use Marketplace\Services\Uploads;
use Symfony\Component\HttpFoundation\Response;

/** Public catalog: home with filters, package detail, thumbnails, downloads. */
class CatalogController
{
    public function __construct(private readonly Catalog $catalog) {}

    public function index(Request $request): View
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'type' => in_array($request->query('type'), ['theme', 'module'], true) ? (string) $request->query('type') : 'all',
            'tier' => in_array($request->query('tier'), ['free', 'premium'], true) ? (string) $request->query('tier') : 'all',
            'category' => (string) $request->query('category', ''),
            'demo' => $request->query('demo') === '1' ? '1' : '',
            'sort' => in_array($request->query('sort'), ['featured', 'updated', 'name', 'price_asc', 'price_desc'], true) ? (string) $request->query('sort') : 'featured',
        ];
        $type = $filters['type'];
        $published = $this->catalog->published()->filter(fn ($p) => $type === 'all' || $p['type'] === $type);
        $categories = Category::query()->where('enabled', true)->orderBy('position')->get()
            ->filter(fn (Category $c) => $type === 'all' || $c->type === 'all' || $c->type === $type)
            ->map(fn (Category $c) => ['slug' => $c->slug, 'name' => $c->name, 'count' => $published->filter(fn ($p) => isset($p['category_labels'][$c->slug]))->count()])
            ->values();
        $current = $categories->firstWhere('slug', $filters['category']);

        $browseUrl = function (array $changes = []) use ($request): string {
            $params = array_replace(array_intersect_key($request->query(), array_flip(['q', 'type', 'tier', 'category', 'demo', 'sort'])), $changes);
            $params = array_filter($params, fn ($v) => is_scalar($v) && (string) $v !== '' && (string) $v !== 'all');

            return url('/').($params === [] ? '' : '?'.http_build_query($params));
        };

        return view('catalog.index', [
            'filters' => $filters,
            'type' => $type,
            'counts' => $this->catalog->counts(),
            'categories' => $categories,
            'typeCount' => $published->count(),
            'result' => $this->catalog->browse($filters, (int) $request->query('page', 1)),
            'heading' => $current['name'] ?? ($type === 'theme' ? 'Storefront themes' : ($type === 'module' ? 'Extensions for your store' : 'Explore the marketplace')),
            'tagline' => (string) (Setting::get('tagline') ?: 'The repository for Modrix Commerce. Choose a storefront theme or an extension, then run it on the core platform.'),
            'currency' => (string) (Setting::get('currency') ?: config('marketplace.currency')),
            'browseUrl' => $browseUrl,
        ]);
    }

    public function show(Request $request, string $slug): View
    {
        $product = $this->catalog->find($slug) ?? abort(404);
        $related = $this->catalog->published()
            ->filter(fn ($p) => $p['slug'] !== $slug && $p['type'] === $product['type'])
            ->sortByDesc(fn ($p) => count(array_intersect($p['categories'], $product['categories'])))
            ->take(3)
            ->values();

        return view('catalog.show', [
            'product' => $product,
            'owned' => $this->catalog->owns($request->user(), $product['model']),
            'related' => $related,
            'currency' => (string) (Setting::get('currency') ?: config('marketplace.currency')),
        ]);
    }

    /** A package thumbnail from private storage. */
    public function media(string $slug, Uploads $uploads): Response
    {
        $path = Package::query()->where('slug', $slug)->value('thumbnail_path');
        $file = is_string($path) ? $uploads->path($path) : null;

        abort_unless($file !== null && is_file($file), 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=86400']);
    }

    /** The marketplace logo uploaded in the admin. */
    public function logo(Uploads $uploads): Response
    {
        $path = Setting::get('logo');
        $file = is_string($path) && $path !== '' ? $uploads->path($path) : null;

        abort_unless($file !== null && is_file($file), 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=3600']);
    }

    /** The newest release ZIP: free packages for everyone, paid ones for license holders. */
    public function download(Request $request, string $slug, Releases $releases): Response
    {
        $product = $this->catalog->find($slug) ?? abort(404);
        $package = $product['model'];

        if (! $package->is_free && ! $this->catalog->owns($request->user(), $package)) {
            abort(403, 'This package is available to customers who own it.');
        }

        $release = $package->latestRelease() ?? abort(404);
        $file = $releases->path($release);
        abort_unless(is_file($file), 404);

        return response()->download($file, $package->slug.'-'.$release->version.'.zip', ['Content-Type' => 'application/zip']);
    }
}

<?php

namespace App\Http\Controllers\Storefront;

use App\Core\Seo\Sitemap;
use App\Core\Seo\SitemapRenderer;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * GET /sitemap.xml — the merged sitemap from every active sitemap provider.
 * Returns 404 when no module contributes a sitemap, so an empty file is never
 * advertised to crawlers. The rendered XML is cached for a short while.
 */
class SitemapController extends Controller
{
    public function __invoke(Sitemap $sitemap, SitemapRenderer $renderer): Response
    {
        abort_unless($sitemap->isActive(), 404);

        $xml = Cache::remember(Sitemap::CACHE_KEY, now()->addHour(), fn () => $renderer->render($sitemap->entries()));

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}

<?php

namespace App\Core\Seo;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * One <url> in the sitemap. Built by a sitemap provider (a module with the
 * "sitemap.provider" capability) and rendered by SitemapRenderer.
 */
final class SitemapEntry
{
    /**
     * @param  list<string>  $images  absolute image URLs (rendered as <image:image>)
     * @param  array<string, string>  $alternates  hreflang => absolute URL
     */
    public function __construct(
        public readonly string $loc,
        public readonly ?CarbonInterface $lastmod = null,
        public readonly ?string $changefreq = null,
        public readonly ?float $priority = null,
        public readonly array $images = [],
        public readonly array $alternates = [],
    ) {}

    /**
     * @param  list<string>  $images
     * @param  array<string, string>  $alternates
     */
    public static function make(string $loc, CarbonInterface|string|null $lastmod = null, ?string $changefreq = null, ?float $priority = null, array $images = [], array $alternates = []): self
    {
        return new self(
            $loc,
            is_string($lastmod) ? Carbon::parse($lastmod) : $lastmod,
            $changefreq,
            $priority,
            array_values(array_filter($images, fn (string $url) => $url !== '')),
            $alternates,
        );
    }
}

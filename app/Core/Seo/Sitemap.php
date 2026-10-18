<?php

namespace App\Core\Seo;

use Closure;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Where modules contribute the storefront's XML sitemap. A module with the
 * "sitemap.provider" capability registers a closure that returns SitemapEntry
 * objects; the core serves the merged set at /sitemap.xml (ADR: modules
 * declare, the core renders). When no provider is active, the endpoint is off.
 *
 * A provider that throws is skipped and logged, so a broken module cannot take
 * the sitemap down.
 */
final class Sitemap
{
    /** URL set limit per the sitemap protocol; a larger catalogue needs an index (not yet provided). */
    public const MAX_URLS = 50000;

    /** Cache key for the rendered XML (the storefront endpoint caches for an hour). */
    public const CACHE_KEY = 'sitemap.xml';

    /** @var array<string, Closure(): iterable<SitemapEntry>> */
    private array $providers = [];

    /**
     * @param  Closure(): iterable<SitemapEntry>  $provider
     */
    public function register(string $module, Closure $provider): void
    {
        $this->providers[$module] = $provider;
    }

    public function isActive(): bool
    {
        return $this->providers !== [];
    }

    /**
     * @return array<string, Closure(): iterable<SitemapEntry>>
     */
    public function providers(): array
    {
        return $this->providers;
    }

    /**
     * Every entry from every provider, capped at the protocol limit.
     *
     * @return \Generator<int, SitemapEntry>
     */
    public function entries(): \Generator
    {
        $count = 0;

        foreach ($this->providers as $module => $provider) {
            try {
                foreach ($provider() as $entry) {
                    yield $entry;
                    $count++;

                    if ($count >= self::MAX_URLS) {
                        return;
                    }
                }
            } catch (Throwable $e) {
                report($e);

                continue;
            }
        }
    }

    /**
     * Count without rendering, for the admin status page.
     */
    public function count(): int
    {
        $count = 0;

        foreach ($this->entries() as $entry) {
            $count++;
        }

        return $count;
    }

    /** Drops the cached XML so the next request rebuilds it. */
    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

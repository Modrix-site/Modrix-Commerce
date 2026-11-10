<?php

namespace App\Core\Themes;

use App\Domains\Catalog\Models\Category;
use App\Domains\Content\Models\Page;

/** Resolve content links against published storefront destinations, once per request. */
final class StorefrontLinks
{
    private ?array $pages = null;

    private ?array $categories = null;

    public function resolve(string $url): ?string
    {
        // External URLs and non-content routes retain their existing behavior.
        if (! str_starts_with($url, '/') || str_starts_with($url, '//')) {
            return $url;
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path)) {
            return null;
        }
        if (preg_match('~^/pages/([^/]+)/?$~', $path, $match)) {
            $this->pages ??= Page::query()->where('status', 'published')->pluck('slug')->all();

            return in_array(rawurldecode($match[1]), $this->pages, true) ? $url : null;
        }
        if (preg_match('~^/c/([^/]+)/?$~', $path, $match)) {
            $this->categories ??= Category::query()->active()->pluck('slug')->all();

            return in_array(rawurldecode($match[1]), $this->categories, true) ? $url : null;
        }

        return $url;
    }

    public function content(array $content): array
    {
        foreach ($content as $key => $value) {
            if (str_ends_with($key, '_link') && is_string($value) && $value !== '' && $this->resolve($value) === null) {
                $label = substr($key, 0, -5).'_label';
                if (array_key_exists($label, $content)) {
                    $content[$key] = '';
                    $content[$label] = '';
                }
            }
        }

        return $content;
    }
}

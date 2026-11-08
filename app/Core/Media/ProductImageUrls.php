<?php

namespace App\Core\Media;

use App\Domains\Catalog\Models\ProductImage;
use Closure;
use Illuminate\Container\Attributes\Singleton;

/**
 * Extension point (capability "catalog.image_urls"): a module registers a
 * resolver that may replace the public URL of a product image. Resolvers run
 * in registration order and the first non-null URL wins; null falls through
 * to the next resolver and finally to the image's own disk URL.
 */
#[Singleton]
final class ProductImageUrls
{
    /** @var array<string, Closure(ProductImage): ?string> */
    private array $resolvers = [];

    /**
     * @param  Closure(ProductImage): ?string  $resolver
     */
    public function register(string $module, Closure $resolver): void
    {
        $this->resolvers[$module] = $resolver;
    }

    public function forget(string $module): void
    {
        unset($this->resolvers[$module]);
    }

    public function resolve(ProductImage $image): ?string
    {
        foreach ($this->resolvers as $resolver) {
            $url = $resolver($image);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }
}

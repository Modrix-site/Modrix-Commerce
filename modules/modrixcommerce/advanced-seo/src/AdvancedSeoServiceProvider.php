<?php

namespace EcoModular\AdvancedSeo;

use App\Core\Extensions\StorefrontSlots;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\ServiceProvider;

/**
 * Publishes SEO tags on every storefront theme and rewrites the title and
 * description props those themes already render.
 */
class AdvancedSeoServiceProvider extends ServiceProvider
{
    public function boot(StorefrontSlots $slots): void
    {
        $kernel = $this->app->make(Kernel::class);

        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(ApplyStorefrontSeo::class);
        }

        $slots->register('storefront.overlay', SeoDocument::MODULE, 'SeoTags', function (): ?array {
            $document = app(SeoDocument::class)->forRequest(request());

            if ($document === null) {
                return null;
            }

            unset($document['apply']);

            return $document;
        });
    }
}

<?php

namespace EcoModular\AdvancedSeo;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Writes the resolved title and description into the props every theme already
 * reads, so home, product, catalog and content pages stay in sync.
 */
final class ApplyStorefrontSeo
{
    public function __construct(private readonly SeoDocument $documents) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $document = $this->documents->forRequest($request);

        if ($document === null || $document['apply'] !== true) {
            return $response;
        }

        if ($response instanceof InertiaResponse) {
            $this->patchInertia($response, $document);

            return $response;
        }

        if ($response instanceof JsonResponse && $response->headers->get('X-Inertia') === 'true') {
            $page = $response->getData(true);

            if (is_array($page)) {
                $response->setData($this->patchPage($page, $document));
            }

            return $response;
        }

        $original = $response instanceof \Illuminate\Http\Response ? $response->getOriginalContent() : null;

        if ($original instanceof View) {
            $page = $original->getData()['page'] ?? null;

            if (is_array($page)) {
                $page = $this->patchPage($page, $document);
                $original->with('page', $page);
                $content = $response->getContent();

                if (is_string($content)) {
                    $encoded = json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_THROW_ON_ERROR);
                    // A callback, not a replacement string: page text such as "$175" or "\1"
                    // would otherwise be read as back-references and corrupted.
                    $updated = preg_replace_callback(
                        '/<script data-page="app" type="application\/json">.*?<\/script>/s',
                        fn () => '<script data-page="app" type="application/json">'.$encoded.'</script>',
                        $content,
                        1,
                    );

                    if (is_string($updated)) {
                        $response->setContent($updated);
                        $property = new \ReflectionProperty($response, 'original');
                        $property->setValue($response, $original);
                    }
                }
            }
        }

        return $response;
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function patchPage(array $page, array $document): array
    {
        if (isset($page['props']) && is_array($page['props'])) {
            $page['props'] = self::patchProps($page['props'], $document);
        }

        return $page;
    }

    /**
     * @param  array<string, mixed>  $document
     */
    private function patchInertia(InertiaResponse $response, array $document): void
    {
        $props = new \ReflectionProperty($response, 'props');
        $shared = new \ReflectionProperty($response, 'sharedProps');
        $pageProps = $props->getValue($response);
        $sharedProps = $shared->getValue($response);

        if (is_array($pageProps)) {
            $props->setValue($response, self::patchProps($pageProps, $document));
        }

        if (! is_array($sharedProps) || ! array_key_exists('storefront', $sharedProps)) {
            return;
        }

        $current = $sharedProps['storefront'];
        $sharedProps['storefront'] = function () use ($current, $document) {
            $storefront = $current instanceof Closure ? $current() : (is_callable($current) ? $current() : $current);

            if (! is_array($storefront)) {
                return $storefront;
            }

            $storefront['seoTitle'] = $document['title'];
            $storefront['seoDescription'] = $document['description'];

            return $storefront;
        };
        $shared->setValue($response, $sharedProps);
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    public static function patchProps(array $props, array $document): array
    {
        if (isset($props['storefront']) && is_array($props['storefront'])) {
            $props['storefront']['seoTitle'] = $document['title'];
            $props['storefront']['seoDescription'] = $document['description'];
        }

        if (isset($props['product']) && is_array($props['product'])) {
            $props['product']['meta_title'] = $document['title'];
            $props['product']['meta_description'] = $document['description'];
        }

        if (isset($props['meta']) && is_array($props['meta'])) {
            $props['meta']['title'] = $document['title'];
            $props['meta']['description'] = $document['description'];
        }

        if (isset($props['page']) && is_array($props['page']) && array_key_exists('meta_title', $props['page'])) {
            $props['page']['meta_title'] = $document['title'];
            $props['page']['meta_description'] = $document['description'];
        }

        return $props;
    }
}

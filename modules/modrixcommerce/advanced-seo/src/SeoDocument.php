<?php

namespace EcoModular\AdvancedSeo;

use App\Core\Extensions\ModuleSettingsSchema;
use App\Core\Settings\SettingsRepository;
use App\Core\Settings\StoreSettings;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Content\Models\Page;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The title, description and crawl tags for the current storefront request.
 * Templates only substitute {tokens}; merchant text is never evaluated.
 */
final class SeoDocument
{
    public const MODULE = 'modrixcommerce/advanced-seo';

    /** @var list<string> */
    private const PUBLIC_ROUTES = [
        'home',
        'storefront.product',
        'storefront.category',
        'storefront.brand',
        'storefront.search',
        'storefront.page',
    ];

    public function __construct(
        private readonly ModuleSettingsSchema $settings,
        private readonly StoreSettings $store,
        private readonly SettingsRepository $repository,
    ) {}

    public function active(): bool
    {
        return (bool) $this->settings->of(self::MODULE, 'enabled', true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function forRequest(Request $request): ?array
    {
        if (! $this->active()) {
            return null;
        }

        $route = $request->route()?->getName();

        if (! is_string($route) || ! $request->isMethod('GET')) {
            return null;
        }

        if (! in_array($route, self::PUBLIC_ROUTES, true)) {
            return $this->privatePage($request, $route);
        }

        $built = match ($route) {
            'home' => $this->home(),
            'storefront.product' => $this->product((string) $request->route('slug')),
            'storefront.category' => $this->category((string) $request->route('slug')),
            'storefront.brand' => $this->brand((string) $request->route('slug')),
            'storefront.search' => $this->search($request),
            'storefront.page' => $this->page((string) $request->route('slug')),
            default => null,
        };

        if ($built === null) {
            return null;
        }

        return $this->finish($request, $built, $this->indexed($request, $route));
    }

    /**
     * @param  array{title: string, description: string, image: ?string, jsonLd: ?array<string, mixed>}  $built
     * @return array<string, mixed>
     */
    private function finish(Request $request, array $built, bool $index): array
    {
        $title = $this->limit($built['title'], $this->limitOf('title_max', 60));
        $description = $this->limit($built['description'], $this->limitOf('description_max', 160));

        return [
            'apply' => true,
            'title' => $title,
            'description' => $description,
            'canonical' => $request->url(),
            'robots' => $index ? 'index, follow' : 'noindex, nofollow',
            'image' => $built['image'],
            'openGraph' => (bool) $this->settings->of(self::MODULE, 'open_graph', true),
            'jsonLd' => (bool) $this->settings->of(self::MODULE, 'json_ld', true) && $built['jsonLd'] !== null
                ? json_encode($built['jsonLd'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS)
                : null,
            'verification' => $this->verification(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function privatePage(Request $request, string $route): ?array
    {
        $private = str_starts_with($route, 'account.')
            || str_starts_with($route, 'storefront.account')
            || in_array($route, ['cart.show', 'checkout.show', 'login', 'register'], true);

        if (! $private || ! (bool) $this->settings->of(self::MODULE, 'noindex_private', true)) {
            return null;
        }

        return [
            'apply' => false,
            'title' => '',
            'description' => '',
            'canonical' => null,
            'robots' => 'noindex, nofollow',
            'image' => null,
            'openGraph' => false,
            'jsonLd' => null,
            'verification' => $this->verification(),
        ];
    }

    /**
     * @return array{title: string, description: string, image: ?string, jsonLd: ?array<string, mixed>}
     */
    private function home(): array
    {
        $vars = $this->storeVars();
        $writtenTitle = trim((string) $this->repository->get('store.meta_title', ''));
        $writtenDescription = trim((string) $this->repository->get('store.meta_description', ''));
        $title = $this->fill((string) $this->settings->of(self::MODULE, 'home_title', '{store}'), $vars);
        $description = $this->fill((string) $this->settings->of(self::MODULE, 'home_description', ''), $vars);

        return [
            'title' => $title !== '' ? $title : ($writtenTitle !== '' ? $writtenTitle : $vars['store']),
            'description' => $description !== '' ? $description : $writtenDescription,
            'image' => null,
            'jsonLd' => [
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $vars['store'],
                'url' => url('/'),
                'potentialAction' => [
                    '@type' => 'SearchAction',
                    'target' => url('/search').'?q={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ],
            ],
        ];
    }

    /**
     * @return array{title: string, description: string, image: ?string, jsonLd: ?array<string, mixed>}|null
     */
    private function product(string $slug): ?array
    {
        $product = Product::query()->visible()->with([
            'brand',
            'images',
            // The storefront's first variant (active, in order), with its prices for {price}.
            'variants' => fn ($query) => $query->where('is_active', true)->orderBy('position')->orderBy('id'),
            'variants.prices',
            'categories',
        ])->where('slug', $slug)->first();

        if (! $product instanceof Product) {
            return null;
        }

        $category = $product->categories->first();
        $variant = $product->variants->first();
        $vars = [
            ...$this->storeVars(),
            'name' => (string) $product->localized('name'),
            'brand' => (string) ($product->brand?->name ?? ''),
            'category' => $category instanceof Category ? (string) $category->localized('name') : '',
            'sku' => $variant instanceof ProductVariant ? (string) $variant->sku : '',
            'price' => $this->price($variant),
            'description' => $this->plain((string) ($product->localized('short_description') ?: $product->localized('description'))),
        ];
        $image = $product->images->first()?->url();

        return [
            'title' => $this->writtenOrTemplate($product->localized('meta_title'), 'product_title', '{name} | {store}', $vars),
            'description' => $this->writtenOrTemplate($product->localized('meta_description'), 'product_description', '{description}', $vars),
            'image' => is_string($image) ? $this->absolute($image) : null,
            'jsonLd' => $this->productJsonLd($product, $vars, is_string($image) ? $this->absolute($image) : null, $variant),
        ];
    }

    /**
     * @param  array<string, string>  $vars
     * @return array<string, mixed>
     */
    private function productJsonLd(Product $product, array $vars, ?string $image, ?ProductVariant $variant): array
    {
        $offer = [
            '@type' => 'Offer',
            'url' => route('storefront.product', $product->slug),
            'priceCurrency' => $this->store->currency(),
            'availability' => 'https://schema.org/InStock',
        ];
        $money = $variant?->priceIn($this->store->currency());

        if ($money !== null) {
            $offer['price'] = (string) $money->amount();
        }

        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $vars['name'],
            'description' => $vars['description'],
            'url' => route('storefront.product', $product->slug),
            'offers' => $offer,
        ];

        if ($image !== null) {
            $data['image'] = [$image];
        }

        if ($vars['sku'] !== '') {
            $data['sku'] = $vars['sku'];
        }

        if ($vars['brand'] !== '') {
            $data['brand'] = ['@type' => 'Brand', 'name' => $vars['brand']];
        }

        return $data;
    }

    /**
     * @return array{title: string, description: string, image: ?string, jsonLd: null}|null
     */
    private function category(string $slug): ?array
    {
        $category = Category::query()->active()->withCurrentTranslation()->where('slug', $slug)->first();

        if (! $category instanceof Category) {
            return null;
        }

        $vars = [
            ...$this->storeVars(),
            'name' => (string) $category->localized('name'),
            'description' => $this->plain((string) ($category->localized('description') ?? '')),
        ];
        $image = $category->imageUrl();

        return [
            'title' => $this->writtenOrTemplate($category->localized('meta_title'), 'category_title', '{name} | {store}', $vars),
            'description' => $this->writtenOrTemplate($category->localized('meta_description'), 'category_description', '{description}', $vars),
            'image' => is_string($image) ? $this->absolute($image) : null,
            'jsonLd' => null,
        ];
    }

    /**
     * @return array{title: string, description: string, image: ?string, jsonLd: null}|null
     */
    private function brand(string $slug): ?array
    {
        $brand = Brand::query()->with('logo')->where('slug', $slug)->first();

        if (! $brand instanceof Brand) {
            return null;
        }

        $vars = [
            ...$this->storeVars(),
            'name' => (string) $brand->name,
            'description' => $this->plain((string) ($brand->description ?? '')),
        ];

        return [
            'title' => $this->fill((string) $this->settings->of(self::MODULE, 'brand_title', '{name} | {store}'), $vars),
            'description' => $vars['description'],
            'image' => $brand->logo?->url() ? $this->absolute($brand->logo->url()) : null,
            'jsonLd' => null,
        ];
    }

    /**
     * @return array{title: string, description: string, image: null, jsonLd: null}
     */
    private function search(Request $request): array
    {
        $query = trim((string) $request->query('q', ''));
        $vars = [...$this->storeVars(), 'query' => $query !== '' ? $query : 'Search'];

        return [
            'title' => $this->fill((string) $this->settings->of(self::MODULE, 'search_title', '{query} | {store}'), $vars),
            'description' => '',
            'image' => null,
            'jsonLd' => null,
        ];
    }

    /**
     * @return array{title: string, description: string, image: null, jsonLd: null}|null
     */
    private function page(string $slug): ?array
    {
        $page = Page::query()->where('status', 'published')->where('slug', $slug)->first();

        if (! $page instanceof Page) {
            return null;
        }

        $vars = [
            ...$this->storeVars(),
            'name' => (string) $page->title,
            'description' => $this->plain((string) ($page->meta_description ?: $page->content)),
        ];

        $writtenDescription = trim((string) ($page->meta_description ?? ''));

        return [
            'title' => $this->writtenOrTemplate($page->meta_title, 'page_title', '{name} | {store}', $vars),
            'description' => $writtenDescription !== '' ? $writtenDescription : $vars['description'],
            'image' => null,
            'jsonLd' => null,
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function writtenOrTemplate(mixed $written, string $key, string $fallback, array $vars): string
    {
        $written = trim((string) ($written ?? ''));

        if ($written !== '' && (bool) $this->settings->of(self::MODULE, 'prefer_written', true)) {
            return $written;
        }

        $filled = $this->fill((string) $this->settings->of(self::MODULE, $key, $fallback), $vars);

        return $filled !== '' ? $filled : $written;
    }

    /**
     * @return array{store: string, tagline: string}
     */
    private function storeVars(): array
    {
        return [
            'store' => $this->store->name(),
            'tagline' => trim((string) $this->repository->get('store.tagline', '')),
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function fill(string $template, array $vars): string
    {
        $text = (string) preg_replace_callback('/\{([a-z]+)\}/', function (array $match) use ($vars): string {
            return $vars[$match[1]] ?? $match[0];
        }, $template);

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function plain(string $value): string
    {
        $text = trim(html_entity_decode(strip_tags($value)));

        return trim(preg_replace('/\s+/', ' ', $text) ?? $text);
    }

    private function limit(string $value, int $max): string
    {
        $value = $this->plain($value);

        if (mb_strlen($value) <= $max) {
            return $value;
        }

        $cut = rtrim(mb_substr($value, 0, $max - 1));
        $space = mb_strrpos($cut, ' ');

        if ($space !== false && $space > (int) ($max * 0.6)) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, '.,;:-|').'…';
    }

    private function limitOf(string $key, int $fallback): int
    {
        $value = (int) $this->settings->of(self::MODULE, $key, $fallback);

        return max(20, min(320, $value));
    }

    private function indexed(Request $request, string $route): bool
    {
        if ($route === 'storefront.search' && (bool) $this->settings->of(self::MODULE, 'noindex_private', true)) {
            return false;
        }

        $filters = collect($request->query->keys())->reject(fn ($key) => in_array($key, ['page', 'lang', 'currency'], true));

        return $filters->isEmpty();
    }

    private function price(?ProductVariant $variant): string
    {
        $money = $variant?->priceIn($this->store->currency());

        return $money === null ? '' : trim((string) $money->amount().' '.$this->store->currency());
    }

    private function absolute(string $url): string
    {
        return Str::startsWith($url, ['http://', 'https://']) ? $url : url($url);
    }

    private function verification(): ?string
    {
        $code = trim((string) $this->settings->of(self::MODULE, 'google_verification', ''));

        return preg_match('/^[A-Za-z0-9_-]{8,120}$/', $code) === 1 ? $code : null;
    }
}

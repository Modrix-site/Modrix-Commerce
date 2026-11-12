<?php

namespace App\Domains\Catalog;

use App\Core\Audit\AuditLogger;
use App\Core\Content\RichText;
use App\Core\Extensions\ManifestValidator;
use App\Core\Settings\StoreSettings;
use App\Core\Themes\ThemeException;
use App\Core\Themes\ThemeManifest;
use App\Core\Themes\ThemeRepository;
use App\Domains\Catalog\Actions\SaveCategory;
use App\Domains\Catalog\Actions\SaveProduct;
use App\Domains\Catalog\Actions\StoreProductImage;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Content\Models\Page;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Imports a theme's demo categories and products (`demo_content`).
 *
 * Additive only: anything whose slug or SKU already exists is skipped, so
 * existing merchant content is never changed. Products go through the
 * normal SaveProduct action (prices in the store currency, stock ledger,
 * audit) and images through the image sanitizer. The caller must have
 * obtained explicit confirmation.
 */
final class ThemeDemoImporter
{
    private const MAX_BYTES = 524288;

    private const MAX_ITEMS = 100;

    public function __construct(
        private readonly SaveCategory $categories,
        private readonly SaveProduct $products,
        private readonly StoreProductImage $images,
        private readonly StoreSettings $store,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return array{categories: int, products: int, pages?: int}
     */
    public function import(ThemeManifest $manifest, User $actor): array
    {
        $data = $this->read($manifest);
        $createdCategories = 0;
        $createdProducts = 0;
        $createdPages = 0;

        DB::transaction(function () use ($data, $manifest, $actor, &$createdCategories, &$createdProducts, &$createdPages) {
            foreach ($data['pages'] as $page) {
                if (Page::withTrashed()->where('slug', $page['slug'])->exists()) {
                    continue;
                }
                Page::create([
                    'title' => $page['title'], 'slug' => $page['slug'],
                    'content' => RichText::clean($page['content']), 'kind' => Page::KIND_PAGE,
                    'status' => 'published', 'meta_title' => $page['title'],
                ]);
                $createdPages++;
            }
            foreach ($data['categories'] as $category) {
                if (Category::query()->where('slug', $category['slug'])->exists()) {
                    continue;
                }

                $this->categories->handle(null, [
                    'name' => $category['name'],
                    'slug' => $category['slug'],
                    'description' => $category['description'] ?? null,
                    'is_active' => true,
                ]);
                $createdCategories++;
            }

            foreach ($data['products'] as $item) {
                if (Product::withTrashed()->where('slug', $item['slug'])->exists() || ProductVariant::withTrashed()->where('sku', $item['sku'])->exists()) {
                    continue;
                }

                $categoryIds = array_values(array_map('intval', Category::query()->whereIn('slug', (array) ($item['categories'] ?? []))->pluck('id')->all()));

                $product = $this->products->handle(null, [
                    'type' => 'simple',
                    'name' => (string) $item['name'],
                    'slug' => (string) $item['slug'],
                    'status' => 'published',
                    'short_description' => isset($item['short_description']) ? (string) $item['short_description'] : null,
                    'description' => isset($item['description']) ? (string) $item['description'] : null,
                    'category_ids' => $categoryIds,
                    'variants' => [[
                        'sku' => (string) $item['sku'],
                        'price' => (string) $item['price'],
                        'compare_at_price' => isset($item['compare_at_price']) ? (string) $item['compare_at_price'] : null,
                        'track_inventory' => true,
                        'initial_stock' => (int) ($item['stock'] ?? 10),
                        'is_active' => true,
                    ]],
                ], $this->store->currency(), $actor);

                if (isset($item['image'])) {
                    $path = $this->assetPath($manifest, $item['image']);
                    $this->images->handle($product, new UploadedFile($path, basename($path), test: true), $item['name']);
                }
                foreach ($item['images'] ?? [] as $image) {
                    $path = $this->assetPath($manifest, $image);
                    $this->images->handle($product, new UploadedFile($path, basename($path), test: true), $item['name']);
                }

                $createdProducts++;
            }
        });

        $this->audit->record('theme.demo_imported', null, ['theme' => $manifest->name, 'categories' => $createdCategories, 'products' => $createdProducts, 'pages' => $createdPages], $actor);

        return ['categories' => $createdCategories, 'products' => $createdProducts] + ($data['pages'] === [] ? [] : ['pages' => $createdPages]);
    }

    /**
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pages: list<array<string, mixed>>}
     */
    private function read(ThemeManifest $manifest): array
    {
        if ($manifest->demoContent === null) {
            throw new ThemeException('This theme has no demo content.');
        }

        $file = ManifestValidator::resolvePath(ThemeRepository::packageDir($manifest->name), $manifest->demoContent, directory: false);

        if ($file === null || filesize($file) > self::MAX_BYTES) {
            throw new ThemeException('The demo content file is missing or too large.');
        }

        try {
            $data = json_decode((string) file_get_contents($file), true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ThemeException('The demo content file is not valid JSON.');
        }

        $categories = is_array($data['categories'] ?? null) ? array_values($data['categories']) : [];
        $products = is_array($data['products'] ?? null) ? array_values($data['products']) : [];
        $pages = is_array($data['pages'] ?? null) ? array_values($data['pages']) : [];

        if (count($categories) + count($products) + count($pages) > self::MAX_ITEMS) {
            throw new ThemeException('The demo content has too many items.');
        }
        foreach ($pages as $i => $page) {
            if (! is_array($page) || ! $this->text($page['title'] ?? null, 160) || ! $this->slug($page['slug'] ?? null)
                || ! $this->text($page['content'] ?? null, 10000)) {
                throw new ThemeException("Demo page #{$i} is invalid.");
            }
        }

        foreach ($categories as $i => $category) {
            if (! is_array($category) || ! $this->text($category['name'] ?? null, 160) || ! $this->slug($category['slug'] ?? null)
                || (isset($category['description']) && ! $this->text($category['description'], 1000))) {
                throw new ThemeException("Demo category #{$i} is invalid.");
            }
        }

        foreach ($products as $i => $product) {
            if (! is_array($product) || ! $this->text($product['name'] ?? null, 191) || ! $this->slug($product['slug'] ?? null)
                || ! is_string($product['sku'] ?? null) || ! preg_match('/^[A-Z0-9._-]{1,64}$/', $product['sku'])
                || ! is_string($product['price'] ?? null) || ! preg_match('/^\d{1,9}(\.\d{1,2})?$/', $product['price'])
                || (isset($product['compare_at_price']) && (! is_string($product['compare_at_price']) || ! preg_match('/^\d{1,9}(\.\d{1,2})?$/', $product['compare_at_price'])))
                || (isset($product['stock']) && (! is_int($product['stock']) || $product['stock'] < 0 || $product['stock'] > 10000))
                || (isset($product['description']) && ! $this->text($product['description'], 5000))
                || (isset($product['short_description']) && ! $this->text($product['short_description'], 500))
                || (isset($product['categories']) && (! is_array($product['categories']) || array_filter($product['categories'], fn ($s) => ! $this->slug($s)) !== []))
                || (isset($product['image']) && (! is_string($product['image']) || $this->assetPath($manifest, $product['image']) === ''))) {
                throw new ThemeException("Demo product #{$i} is invalid.");
            }
            if (isset($product['images']) && (! is_array($product['images']) || ! array_is_list($product['images']) || count($product['images']) > 8
                || array_filter($product['images'], fn ($image) => ! is_string($image) || $this->assetPath($manifest, $image) === '') !== [])) {
                throw new ThemeException("Demo product #{$i} has invalid gallery images.");
            }
        }

        /** @var array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, pages: list<array<string, mixed>>} */
        return ['categories' => $categories, 'products' => $products, 'pages' => $pages];
    }

    private function assetPath(ThemeManifest $manifest, string $path): string
    {
        $dir = ThemeRepository::packageDir($manifest->name);
        $assets = $manifest->assets === null ? null : ManifestValidator::resolvePath($dir, $manifest->assets, directory: true);
        $file = ManifestValidator::resolvePath($dir, $path, directory: false);

        return $assets !== null && $file !== null && str_starts_with($file, $assets.DIRECTORY_SEPARATOR) ? $file : '';
    }

    private function text(mixed $value, int $max): bool
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= $max;
    }

    private function slug(mixed $value): bool
    {
        return is_string($value) && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) && strlen($value) <= 191;
    }
}

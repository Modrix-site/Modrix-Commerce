<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\ProductRelations;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Copies a product as a hidden draft ("Copy of …"): details, variants with
 * prices and options, images (the same media), categories, tags, brand,
 * translations, related products and digital files. Stock starts at 0;
 * barcodes, reviews and sales are not copied.
 */
final class DuplicateProduct
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ProductRelations $relations,
    ) {}

    public function handle(Product $source, User $actor): Product
    {
        $source->loadMissing(['variants.prices', 'variants.options', 'images', 'categories', 'tags', 'translations', 'files']);

        /** @var list<string> $copiedPaths */
        $copiedPaths = [];

        try {
            return DB::transaction(function () use ($source, $actor, &$copiedPaths) {
                $copy = $source->replicate(['slug', 'status', 'published_at']);
                $copy->forceFill([
                    'name' => 'Copy of '.$source->name,
                    'slug' => self::uniqueSlug($source->slug),
                    'status' => ProductStatus::Draft,
                    'published_at' => null,
                ])->save();

                $variantIds = [];

                foreach ($source->variants as $variant) {
                    $newVariant = $variant->replicate(['sku', 'barcode']);
                    $newVariant->forceFill([
                        'product_id' => $copy->id,
                        'sku' => $variant->sku === '' ? $variant->sku : self::uniqueSku($variant->sku),
                        'barcode' => null,
                    ])->save();
                    $variantIds[$variant->id] = $newVariant->id;

                    foreach ($variant->prices as $price) {
                        $newVariant->prices()->create($price->only(['currency', 'amount', 'compare_at_amount']));
                    }

                    $newVariant->options()->sync($variant->options->pluck('id')->all());
                }

                $imageIds = [];

                foreach ($source->images as $image) {
                    $newImage = $image->replicate();
                    $newImage->forceFill([
                        'product_id' => $copy->id,
                        'variant_id' => $image->variant_id === null ? null : ($variantIds[$image->variant_id] ?? null),
                    ])->save();
                    $imageIds[$image->id] = $newImage->id;
                }

                // Variants that point at an image (when the store has that column) keep pointing at the copy.
                foreach ($source->variants as $variant) {
                    $imageId = $variant->getAttribute('image_id');

                    if ($imageId !== null && isset($imageIds[$imageId])) {
                        ProductVariant::query()->whereKey($variantIds[$variant->id])->update(['image_id' => $imageIds[$imageId]]);
                    }
                }

                $copy->categories()->sync($source->categories->pluck('id')->all());

                foreach ($source->tags as $tag) {
                    $copy->tags()->create(['tag' => $tag->tag]);
                }

                foreach ($source->translations as $translation) {
                    $newTranslation = $translation->replicate();
                    $newTranslation->forceFill([
                        'product_id' => $copy->id,
                        'name' => $translation->name === null ? null : 'Copy of '.$translation->name,
                    ])->save();
                }

                $this->relations->sync(
                    $copy,
                    array_values(array_map('intval', $source->relatedProducts()->pluck('products.id')->all())),
                    array_values(array_map('intval', $source->upsellProducts()->pluck('products.id')->all())),
                );

                foreach ($source->files as $file) {
                    $path = "downloads/{$copy->id}/".basename($file->path);
                    Storage::disk($file->disk)->copy($file->path, $path);
                    $copiedPaths[] = $file->disk.':'.$path;
                    $copy->files()->create([...$file->only(['name', 'disk', 'size', 'mime', 'position']), 'path' => $path]);
                }

                $this->audit->record('product.duplicated', $copy, ['source_id' => $source->id, 'copy_id' => $copy->id], $actor);

                return $copy;
            });
        } catch (\Throwable $e) {
            // The rows are rolled back; remove the file copies too.
            foreach ($copiedPaths as $copied) {
                [$disk, $path] = explode(':', $copied, 2);
                Storage::disk($disk)->delete($path);
            }

            throw $e;
        }
    }

    /** "trail-mug-copy", then "trail-mug-copy-2", … (soft-deleted products count). */
    public static function uniqueSlug(string $slug): string
    {
        $base = mb_substr($slug, 0, 180).'-copy';
        $candidate = $base;

        for ($i = 2; Product::withTrashed()->where('slug', $candidate)->exists(); $i++) {
            $candidate = "{$base}-{$i}";
        }

        return $candidate;
    }

    /** "MUG-1-COPY", then "MUG-1-COPY-2", … (soft-deleted variants count). */
    public static function uniqueSku(string $sku): string
    {
        $base = mb_substr($sku, 0, 50).'-COPY';
        $candidate = $base;

        for ($i = 2; ProductVariant::withTrashed()->where('sku', $candidate)->exists(); $i++) {
            $candidate = "{$base}-{$i}";
        }

        return $candidate;
    }
}

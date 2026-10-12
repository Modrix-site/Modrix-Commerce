<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class ManageProductImages
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  list<int>  $orderedIds  every image id of the product, in the desired order
     */
    public function reorder(Product $product, array $orderedIds): void
    {
        $current = $product->images()->pluck('id')->sort()->values()->all();
        $requested = collect($orderedIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($current !== $requested) {
            throw ValidationException::withMessages(['images' => 'The image list is out of date. Reload the page and try again.']);
        }

        DB::transaction(function () use ($product, $orderedIds) {
            foreach ($orderedIds as $position => $id) {
                $product->images()->whereKey($id)->update(['position' => $position]);
            }
        });
    }

    public function updateAlt(ProductImage $image, ?string $alt): void
    {
        $image->update(['alt' => $alt]);
    }

    public function delete(ProductImage $image): void
    {
        DB::transaction(function () use ($image) {
            $image->delete();
            $this->audit->record('product.image_removed', $image->product, ['image_id' => $image->id]);
        });

        // Remove the file only after the row is gone; a missing file is harmless, a dangling row is not.
        Storage::disk($image->disk)->delete($image->path);
    }
}

<?php

namespace App\Domains\Catalog\Actions;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Soft-deletes a product (and its variants). Order history keeps its own
 * snapshots, and inventory history stays intact, so nothing financial is lost.
 */
final class DeleteProduct
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(Product $product): void
    {
        DB::transaction(function () use ($product) {
            $product->forceFill(['status' => ProductStatus::Archived])->save();
            $product->variants()->get()->each->delete();
            $product->delete();

            $this->audit->record('product.deleted', $product, ['name' => $product->name]);
        });
    }
}

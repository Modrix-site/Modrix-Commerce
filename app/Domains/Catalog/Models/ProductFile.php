<?php

namespace App\Domains\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A file a digital product delivers. Stored on a private disk and only ever
 * streamed through a customer's download link.
 *
 * @property int $id
 * @property int $product_id
 * @property string $name
 * @property string $disk
 * @property string $path
 * @property int $size
 * @property string|null $mime
 * @property int $position
 */
#[Fillable(['name', 'disk', 'path', 'size', 'mime', 'position'])]
class ProductFile extends Model
{
    /** Most files per product. */
    public const MAX_PER_PRODUCT = 10;

    /** Largest upload, in KB (500 MB). */
    public const MAX_KB = 512000;

    /** Private disk: never web-served. */
    public const DISK = 'local';

    protected static function booted(): void
    {
        static::deleting(function (ProductFile $file) {
            Storage::disk($file->disk)->delete($file->path);
        });
    }

    protected function casts(): array
    {
        return ['size' => 'integer', 'position' => 'integer'];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Whether the stored file is still there. */
    public function isStored(): bool
    {
        return Storage::disk($this->disk)->exists($this->path);
    }
}

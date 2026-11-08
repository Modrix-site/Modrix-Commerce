<?php

namespace EcoModular\ImageStudio\Models;

use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $product_image_id
 * @property string $status
 * @property string $disk
 * @property string|null $path
 * @property string|null $driver
 * @property string|null $error
 * @property int|null $bytes_before
 * @property int|null $bytes_after
 * @property int|null $width
 * @property int|null $height
 */
class ImageVariant extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const DONE = 'done';

    public const FAILED = 'failed';

    public const SKIPPED = 'skipped';

    protected $table = 'image_studio_variants';

    protected $guarded = [];

    /**
     * @return BelongsTo<ProductImage, $this>
     */
    public function image(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    public function url(): ?string
    {
        return $this->path === null || $this->path === '' ? null : Storage::disk($this->disk)->url($this->path);
    }
}

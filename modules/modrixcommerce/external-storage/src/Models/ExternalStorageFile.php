<?php

namespace EcoModular\ExternalStorage\Models;

use App\Core\Media\Media;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ExternalStorage\Support\ExternalStorageSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks product photos, product videos, and media assets stored in external cloud storage.
 */
class ExternalStorageFile extends Model
{
    public const TYPE_PHOTO = 'photo';
    public const TYPE_VIDEO = 'video';
    public const TYPE_DOCUMENT = 'document';

    public const STATUS_SYNCED = 'synced';
    public const STATUS_PENDING = 'pending';
    public const STATUS_FAILED = 'failed';

    protected $table = 'external_storage_files';

    protected $fillable = [
        'product_id',
        'product_image_id',
        'media_id',
        'media_type',
        'title',
        'provider',
        'bucket',
        'remote_path',
        'remote_url',
        'local_disk',
        'local_path',
        'filename',
        'mime_type',
        'file_size',
        'local_deleted',
        'status',
        'error_message',
        'position',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'product_image_id' => 'integer',
            'media_id' => 'integer',
            'file_size' => 'integer',
            'local_deleted' => 'boolean',
            'position' => 'integer',
            'synced_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productImage(): BelongsTo
    {
        return $this->belongsTo(ProductImage::class, 'product_image_id');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    /**
     * Resolves the public URL for this external file, honoring any updated CDN domain.
     */
    public function publicUrl(): string
    {
        $settings = app(ExternalStorageSettings::class);
        $cdnUrl = $settings->cdnUrl();

        if ($cdnUrl !== '' && $this->remote_path !== '') {
            return rtrim($cdnUrl, '/').'/'.ltrim($this->remote_path, '/');
        }

        return $this->remote_url;
    }

    public function isVideo(): bool
    {
        return $this->media_type === self::TYPE_VIDEO || str_starts_with($this->mime_type, 'video/');
    }

    public function isPhoto(): bool
    {
        return $this->media_type === self::TYPE_PHOTO || str_starts_with($this->mime_type, 'image/');
    }
}

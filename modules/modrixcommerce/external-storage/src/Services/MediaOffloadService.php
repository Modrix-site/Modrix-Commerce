<?php

namespace EcoModular\ExternalStorage\Services;

use App\Core\Media\Media;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ExternalStorage\Models\ExternalStorageFile;
use EcoModular\ExternalStorage\Support\ExternalStorageSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Handles automatic and manual offloading of product photos, product videos,
 * and media library files to external cloud storage (Wasabi, AWS S3, Cloudflare, etc.).
 */
class MediaOffloadService
{
    /** @var array<int, string|null>|null */
    private ?array $imageUrlMap = null;

    public function __construct(
        private readonly ExternalStorageSettings $settings,
        private readonly ExternalStorageClient $client,
    ) {}

    /**
     * Resolves the external storage URL for a ProductImage if synced.
     */
    public function resolveProductImageUrl(ProductImage $image): ?string
    {
        if (! $this->settings->enabled()) {
            return null;
        }

        if ($this->imageUrlMap === null) {
            $this->imageUrlMap = ExternalStorageFile::query()
                ->whereNotNull('product_image_id')
                ->where('status', ExternalStorageFile::STATUS_SYNCED)
                ->get(['product_image_id', 'remote_path', 'remote_url'])
                ->mapWithKeys(fn (ExternalStorageFile $file) => [
                    (int) $file->product_image_id => $file->publicUrl(),
                ])
                ->all();
        }

        if (isset($this->imageUrlMap[$image->id])) {
            return $this->imageUrlMap[$image->id];
        }

        if ($image->disk === 'external_storage') {
            return $this->client->publicUrl($image->path);
        }

        return null;
    }

    public function forgetCache(): void
    {
        $this->imageUrlMap = null;
    }

    /**
     * Automatically or manually offloads a ProductImage to external storage.
     */
    public function offloadProductImage(ProductImage $image): ?ExternalStorageFile
    {
        $this->forgetCache();

        $remotePath = $this->client->normalizeRemotePath($image->path);
        $provider = $this->settings->provider();
        $bucket = $this->settings->bucket();
        $filename = basename($image->path);
        $mimeType = $this->guessMime($image->path);

        // Case 1: Image was already written directly to the `external_storage` disk
        // (when save_mode === 'external_only' switched ecomodular.media_disk to 'external_storage').
        if ($image->disk === 'external_storage') {
            $remoteUrl = $this->client->publicUrl($remotePath);

            /** @var ExternalStorageFile $record */
            $record = ExternalStorageFile::query()->updateOrCreate(
                ['product_image_id' => $image->id],
                [
                    'product_id' => $image->product_id,
                    'media_type' => ExternalStorageFile::TYPE_PHOTO,
                    'title' => $image->alt,
                    'provider' => $provider,
                    'bucket' => $bucket,
                    'remote_path' => $remotePath,
                    'remote_url' => $remoteUrl,
                    'local_disk' => 'external_storage',
                    'local_path' => $image->path,
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'file_size' => 0,
                    'local_deleted' => true,
                    'status' => ExternalStorageFile::STATUS_SYNCED,
                    'error_message' => null,
                    'position' => $image->position ?? 0,
                    'synced_at' => now(),
                ]
            );

            return $record;
        }

        // Case 2: Image is on a local disk (e.g. 'public') and needs to be pushed to external storage.
        if (! $this->settings->isConfigured()) {
            return null;
        }

        $disk = Storage::disk($image->disk);
        if (! $disk->exists($image->path)) {
            return null;
        }

        try {
            $bytes = (string) $disk->get($image->path);
            $size = strlen($bytes);
            $uploaded = $this->client->putObject($image->path, $bytes, $mimeType);

            $localDeleted = false;
            if ($this->settings->shouldSkipOrDeleteLocal()) {
                $disk->delete($image->path);
                $localDeleted = true;
            }

            /** @var ExternalStorageFile $record */
            $record = ExternalStorageFile::query()->updateOrCreate(
                ['product_image_id' => $image->id],
                [
                    'product_id' => $image->product_id,
                    'media_type' => ExternalStorageFile::TYPE_PHOTO,
                    'title' => $image->alt,
                    'provider' => $uploaded['provider'],
                    'bucket' => $uploaded['bucket'],
                    'remote_path' => $uploaded['remote_path'],
                    'remote_url' => $uploaded['remote_url'],
                    'local_disk' => $image->disk,
                    'local_path' => $image->path,
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'file_size' => $size,
                    'local_deleted' => $localDeleted,
                    'status' => ExternalStorageFile::STATUS_SYNCED,
                    'error_message' => null,
                    'position' => $image->position ?? 0,
                    'synced_at' => now(),
                ]
            );

            return $record;
        } catch (Throwable $e) {
            /** @var ExternalStorageFile $failed */
            $failed = ExternalStorageFile::query()->updateOrCreate(
                ['product_image_id' => $image->id],
                [
                    'product_id' => $image->product_id,
                    'media_type' => ExternalStorageFile::TYPE_PHOTO,
                    'title' => $image->alt,
                    'provider' => $provider,
                    'bucket' => $bucket,
                    'remote_path' => $remotePath,
                    'remote_url' => $this->client->publicUrl($remotePath),
                    'local_disk' => $image->disk,
                    'local_path' => $image->path,
                    'filename' => $filename,
                    'mime_type' => $mimeType,
                    'file_size' => 0,
                    'local_deleted' => false,
                    'status' => ExternalStorageFile::STATUS_FAILED,
                    'error_message' => mb_substr($e->getMessage(), 0, 480),
                    'position' => $image->position ?? 0,
                ]
            );

            return $failed;
        }
    }

    /**
     * Automatically or manually offloads a Media library item to external storage.
     */
    public function offloadMedia(Media $media): ?ExternalStorageFile
    {
        $remotePath = $this->client->normalizeRemotePath($media->path);
        $provider = $this->settings->provider();
        $bucket = $this->settings->bucket();
        $mediaType = str_starts_with($media->mime, 'video/')
            ? ExternalStorageFile::TYPE_VIDEO
            : (str_starts_with($media->mime, 'image/') ? ExternalStorageFile::TYPE_PHOTO : ExternalStorageFile::TYPE_DOCUMENT);

        if ($media->disk === 'external_storage') {
            /** @var ExternalStorageFile $record */
            $record = ExternalStorageFile::query()->updateOrCreate(
                ['media_id' => $media->id],
                [
                    'media_type' => $mediaType,
                    'title' => $media->alt ?: $media->filename,
                    'provider' => $provider,
                    'bucket' => $bucket,
                    'remote_path' => $remotePath,
                    'remote_url' => $this->client->publicUrl($remotePath),
                    'local_disk' => 'external_storage',
                    'local_path' => $media->path,
                    'filename' => $media->filename,
                    'mime_type' => $media->mime,
                    'file_size' => $media->size,
                    'local_deleted' => true,
                    'status' => ExternalStorageFile::STATUS_SYNCED,
                    'error_message' => null,
                    'synced_at' => now(),
                ]
            );

            return $record;
        }

        if (! $this->settings->isConfigured()) {
            return null;
        }

        $disk = Storage::disk($media->disk);
        if (! $disk->exists($media->path)) {
            return null;
        }

        try {
            $bytes = (string) $disk->get($media->path);
            $uploaded = $this->client->putObject($media->path, $bytes, $media->mime);

            $localDeleted = false;
            if ($this->settings->shouldSkipOrDeleteLocal()) {
                $disk->delete($media->path);
                $localDeleted = true;
            }

            /** @var ExternalStorageFile $record */
            $record = ExternalStorageFile::query()->updateOrCreate(
                ['media_id' => $media->id],
                [
                    'media_type' => $mediaType,
                    'title' => $media->alt ?: $media->filename,
                    'provider' => $uploaded['provider'],
                    'bucket' => $uploaded['bucket'],
                    'remote_path' => $uploaded['remote_path'],
                    'remote_url' => $uploaded['remote_url'],
                    'local_disk' => $media->disk,
                    'local_path' => $media->path,
                    'filename' => $media->filename,
                    'mime_type' => $media->mime,
                    'file_size' => $media->size ?: strlen($bytes),
                    'local_deleted' => $localDeleted,
                    'status' => ExternalStorageFile::STATUS_SYNCED,
                    'error_message' => null,
                    'synced_at' => now(),
                ]
            );

            return $record;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Uploads a product photo or product video directly to external storage
     * (without saving locally when save_mode is 'external_only').
     */
    public function uploadDirectFile(
        UploadedFile $file,
        ?int $productId = null,
        ?string $title = null,
        ?string $forcedMediaType = null
    ): ExternalStorageFile {
        $this->forgetCache();

        $mime = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream');
        $originalName = $file->getClientOriginalName() ?: 'upload.bin';
        $ext = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION) ?: 'bin');

        $mediaType = $forcedMediaType ?? (str_starts_with($mime, 'video/') || in_array($ext, ['mp4', 'webm', 'mov', 'm4v', 'ogg'], true)
            ? ExternalStorageFile::TYPE_VIDEO
            : (str_starts_with($mime, 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true)
                ? ExternalStorageFile::TYPE_PHOTO
                : ExternalStorageFile::TYPE_DOCUMENT));

        $folder = $mediaType === ExternalStorageFile::TYPE_VIDEO ? 'products/videos' : 'products/photos';
        if ($productId !== null) {
            $folder .= '/'.$productId;
        }

        $relativePath = $folder.'/'.Str::ulid()->toBase32().'.'.$ext;
        $bytes = (string) $file->get();
        $size = strlen($bytes);

        $uploaded = $this->client->putObject($relativePath, $bytes, $mime);

        $localDisk = 'external_storage';
        $localDeleted = true;

        // If hybrid mode is selected, also keep a local backup copy on the 'public' disk.
        if (! $this->settings->shouldSkipOrDeleteLocal()) {
            Storage::disk('public')->put($relativePath, $bytes, ['visibility' => 'public']);
            $localDisk = 'public';
            $localDeleted = false;
        }

        $productImageId = null;
        if ($mediaType === ExternalStorageFile::TYPE_PHOTO && $productId !== null && Product::query()->whereKey($productId)->exists()) {
            $nextPosition = ((int) ProductImage::query()->where('product_id', $productId)->max('position')) + 1;

            // Create the ProductImage without re-triggering duplicate upload
            $productImage = ProductImage::withoutEvents(function () use ($productId, $localDisk, $relativePath, $title, $originalName, $nextPosition) {
                return ProductImage::query()->forceCreate([
                    'product_id' => $productId,
                    'disk' => $localDisk,
                    'path' => $relativePath,
                    'alt' => $title ?: pathinfo($originalName, PATHINFO_FILENAME),
                    'position' => $nextPosition,
                ]);
            });

            $productImageId = $productImage->id;
        }

        $nextFilePos = $productId !== null
            ? ((int) ExternalStorageFile::query()->where('product_id', $productId)->max('position')) + 1
            : 0;

        /** @var ExternalStorageFile $record */
        $record = ExternalStorageFile::query()->create([
            'product_id' => $productId,
            'product_image_id' => $productImageId,
            'media_type' => $mediaType,
            'title' => $title ?: pathinfo($originalName, PATHINFO_FILENAME),
            'provider' => $uploaded['provider'],
            'bucket' => $uploaded['bucket'],
            'remote_path' => $uploaded['remote_path'],
            'remote_url' => $uploaded['remote_url'],
            'local_disk' => $localDisk,
            'local_path' => $relativePath,
            'filename' => $originalName,
            'mime_type' => $mime,
            'file_size' => $size,
            'local_deleted' => $localDeleted,
            'status' => ExternalStorageFile::STATUS_SYNCED,
            'error_message' => null,
            'position' => $nextFilePos,
            'synced_at' => now(),
        ]);

        return $record;
    }

    /**
     * Bulk-offloads all unsynced or failed local product images (and optionally for a specific product).
     *
     * @return array{synced: int, failed: int}
     */
    public function syncLocalImages(?int $productId = null): array
    {
        $synced = 0;
        $failed = 0;

        $syncedImageIds = ExternalStorageFile::query()
            ->whereNotNull('product_image_id')
            ->where('status', ExternalStorageFile::STATUS_SYNCED)
            ->pluck('product_image_id');

        $query = ProductImage::query()->whereNotIn('id', $syncedImageIds);
        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        foreach ($query->cursor() as $image) {
            $result = $this->offloadProductImage($image);
            if ($result instanceof ExternalStorageFile && $result->status === ExternalStorageFile::STATUS_SYNCED) {
                $synced++;
            } else {
                $failed++;
            }
        }

        return ['synced' => $synced, 'failed' => $failed];
    }

    /**
     * Deletes an external storage file record, removing it from external storage (and ProductImage if linked).
     */
    public function deleteExternalFile(ExternalStorageFile $file, bool $deleteProductImage = false): void
    {
        $this->forgetCache();

        if ($file->remote_path !== '') {
            $this->client->deleteObject($file->remote_path);
        }

        if ($deleteProductImage && $file->product_image_id !== null) {
            ProductImage::withoutEvents(function () use ($file) {
                ProductImage::query()->whereKey($file->product_image_id)->delete();
            });
        }

        $file->delete();
    }

    /**
     * Dashboard statistics for the External Storage admin page.
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $totalProductImages = ProductImage::query()->count();
        $syncedFiles = ExternalStorageFile::query()->where('status', ExternalStorageFile::STATUS_SYNCED)->count();
        $syncedPhotos = ExternalStorageFile::query()
            ->where('status', ExternalStorageFile::STATUS_SYNCED)
            ->where('media_type', ExternalStorageFile::TYPE_PHOTO)
            ->count();
        $syncedVideos = ExternalStorageFile::query()
            ->where('status', ExternalStorageFile::STATUS_SYNCED)
            ->where('media_type', ExternalStorageFile::TYPE_VIDEO)
            ->count();
        $failedFiles = ExternalStorageFile::query()->where('status', ExternalStorageFile::STATUS_FAILED)->count();
        $totalBytesOffloaded = (int) ExternalStorageFile::query()
            ->where('status', ExternalStorageFile::STATUS_SYNCED)
            ->sum('file_size');
        $localDeletedCount = ExternalStorageFile::query()
            ->where('status', ExternalStorageFile::STATUS_SYNCED)
            ->where('local_deleted', true)
            ->count();

        $unsyncedLocalImages = ProductImage::query()
            ->whereNotIn(
                'id',
                ExternalStorageFile::query()
                    ->whereNotNull('product_image_id')
                    ->where('status', ExternalStorageFile::STATUS_SYNCED)
                    ->select('product_image_id')
            )
            ->count();

        return [
            'totalProductImages' => $totalProductImages,
            'syncedFiles' => $syncedFiles,
            'syncedPhotos' => $syncedPhotos,
            'syncedVideos' => $syncedVideos,
            'failedFiles' => $failedFiles,
            'unsyncedLocalImages' => $unsyncedLocalImages,
            'totalBytesOffloaded' => $totalBytesOffloaded,
            'localDeletedCount' => $localDeletedCount,
        ];
    }

    private function guessMime(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'avif' => 'image/avif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mov' => 'video/quicktime',
            default => 'application/octet-stream',
        };
    }
}

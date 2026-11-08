<?php

namespace EcoModular\ImageStudio\Services;

use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ImageStudio\Jobs\ProcessProductImage;
use EcoModular\ImageStudio\Models\ImageVariant;
use Illuminate\Support\Facades\Storage;

/**
 * Queues, reverts and summarises product image variants.
 */
class ImageStudio
{
    /**
     * Upsert the variant row to pending without dispatching a job (used by the
     * inline CLI path; queue() builds on it).
     */
    public function markPending(ProductImage $image): ImageVariant
    {
        $variant = ImageVariant::query()->firstOrNew(['product_image_id' => $image->id]);
        $variant->disk = $variant->disk ?: $image->disk;
        $variant->status = ImageVariant::PENDING;
        $variant->error = null;
        $variant->save();

        return $variant;
    }

    public function queue(ProductImage $image): ImageVariant
    {
        $variant = $this->markPending($image);

        ProcessProductImage::dispatch($image->id)->afterCommit();

        return $variant;
    }

    /**
     * @param  iterable<ProductImage>  $images
     */
    public function queueMany(iterable $images): int
    {
        $count = 0;
        foreach ($images as $image) {
            $this->queue($image);
            $count++;
        }

        return $count;
    }

    public function revert(ImageVariant $variant): void
    {
        $this->deleteFile($variant->disk, $variant->path);
        $variant->delete();
    }

    public function revertAll(): int
    {
        $count = 0;
        ImageVariant::query()->chunkById(200, function ($variants) use (&$count): void {
            foreach ($variants as $variant) {
                $this->revert($variant);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @return array{pending: int, processing: int, done: int, failed: int, skipped: int, bytes_saved: int}
     */
    public function stats(): array
    {
        $counts = ImageVariant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $saved = (int) ImageVariant::query()->where('status', ImageVariant::DONE)
            ->selectRaw('coalesce(sum(bytes_before - bytes_after), 0) as saved')->value('saved');

        return [
            'pending' => (int) ($counts[ImageVariant::PENDING] ?? 0),
            'processing' => (int) ($counts[ImageVariant::PROCESSING] ?? 0),
            'done' => (int) ($counts[ImageVariant::DONE] ?? 0),
            'failed' => (int) ($counts[ImageVariant::FAILED] ?? 0),
            'skipped' => (int) ($counts[ImageVariant::SKIPPED] ?? 0),
            'bytes_saved' => max(0, $saved),
        ];
    }

    public function deleteFile(?string $disk, ?string $path): void
    {
        if ($disk === null || $disk === '' || $path === null || $path === '') {
            return;
        }

        try {
            Storage::disk($disk)->delete($path);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

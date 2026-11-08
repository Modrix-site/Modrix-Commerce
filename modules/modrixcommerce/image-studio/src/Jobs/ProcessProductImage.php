<?php

namespace EcoModular\ImageStudio\Jobs;

use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ImageStudio\Exceptions\SkipImage;
use EcoModular\ImageStudio\Models\ImageVariant;
use EcoModular\ImageStudio\Services\ImageProcessor;
use EcoModular\ImageStudio\Services\ImageStudio;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Throwable;

class ProcessProductImage implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $productImageId) {}

    public function uniqueId(): string
    {
        return (string) $this->productImageId;
    }

    public function handle(ImageProcessor $processor, ImageStudio $studio): void
    {
        // A revert + reprocess while this job runs swaps the row under us (the new
        // dispatch is dropped by the unique lock), so pick up the replacement here.
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if (! $this->processCurrentRow($processor, $studio)) {
                return;
            }
        }
    }

    /**
     * Never creates a variant row: rows come from ImageStudio::markPending(). Every
     * outcome is a conditional update on the row's key, so a row reverted or
     * cascaded away mid-run is never resurrected and the file this run wrote is
     * removed.
     *
     * @return bool true when the row vanished mid-run but a replacement row exists (run again)
     */
    private function processCurrentRow(ImageProcessor $processor, ImageStudio $studio): bool
    {
        $image = ProductImage::query()->find($this->productImageId);
        if (! $image instanceof ProductImage) {
            return false;
        }

        $variant = ImageVariant::query()->where('product_image_id', $image->id)->first();
        if (! $variant instanceof ImageVariant) {
            return false;
        }

        $previous = [$variant->disk, $variant->path];
        $claimed = ImageVariant::query()->whereKey($variant->getKey())->update([
            'disk' => $image->disk,
            'status' => ImageVariant::PROCESSING,
            'error' => null,
        ]);
        if ($claimed === 0) {
            return $this->replacementExists();
        }

        try {
            $result = $processor->process($image);
        } catch (SkipImage $e) {
            $studio->deleteFile($previous[0], $previous[1]);
            $saved = ImageVariant::query()->whereKey($variant->getKey())->update(['status' => ImageVariant::SKIPPED, 'path' => null, 'error' => Str::limit($e->getMessage(), 500, '')]);

            return $saved === 0 && $this->replacementExists();
        } catch (Throwable $e) {
            $studio->deleteFile($previous[0], $previous[1]);
            $saved = ImageVariant::query()->whereKey($variant->getKey())->update(['status' => ImageVariant::FAILED, 'path' => null, 'error' => Str::limit($e->getMessage(), 500, '')]);
            if ($saved === 0) {
                // The row is gone: nothing left to retry for.
                return $this->replacementExists();
            }

            throw $e;
        }

        $saved = ImageVariant::query()->whereKey($variant->getKey())->update([
            'status' => ImageVariant::DONE,
            'disk' => $image->disk,
            'path' => $result->path,
            'driver' => $result->driver,
            'error' => null,
            'bytes_before' => $result->bytesBefore,
            'bytes_after' => $result->bytesAfter,
            'width' => $result->width,
            'height' => $result->height,
        ]);
        if ($saved === 0) {
            // Reverted or the product image was deleted mid-run: drop what we just wrote.
            $studio->deleteFile($image->disk, $result->path);

            return $this->replacementExists();
        }

        if ($previous[1] !== null && $previous[1] !== $result->path) {
            $studio->deleteFile($previous[0], $previous[1]);
        }

        return false;
    }

    private function replacementExists(): bool
    {
        return ImageVariant::query()->where('product_image_id', $this->productImageId)->exists();
    }

    public function failed(?Throwable $exception): void
    {
        $variant = ImageVariant::query()->where('product_image_id', $this->productImageId)->first();
        $variant?->update([
            'status' => ImageVariant::FAILED,
            'error' => Str::limit($exception?->getMessage() ?? 'Processing failed', 500, ''),
        ]);
    }
}

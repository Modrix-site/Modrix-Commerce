<?php

namespace App\Core\Media;

use App\Core\Audit\AuditLogger;
use App\Core\Settings\SettingsRepository;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The media library: every stored image in one place, with where it is
 * used. Uploads are re-encoded by the ImageSanitizer. An image in use by a
 * product or a theme setting cannot be deleted.
 */
final class MediaLibrary
{
    public function __construct(
        private readonly ImageSanitizer $sanitizer,
        private readonly SettingsRepository $settings,
        private readonly AuditLogger $audit,
    ) {}

    public static function disk(): string
    {
        return (string) config('ecomodular.media_disk', 'public');
    }

    public function upload(UploadedFile $file, ?string $alt, ?User $actor): Media
    {
        $clean = $this->sanitizer->sanitize($file->getRealPath() ?: '', 'files');
        $path = 'library/'.Str::random(40).'.'.$clean['extension'];
        Storage::disk(self::disk())->put($path, $clean['bytes'], ['visibility' => 'public']);

        $media = $this->register(self::disk(), $path, Media::LIBRARY, $file->getClientOriginalName(), $clean['width'], $clean['height'], strlen($clean['bytes']), $alt, $actor);
        $this->audit->record('media.uploaded', null, ['media' => $media->id, 'file' => $media->filename], $actor);

        return $media;
    }

    /**
     * Records a stored file in the library (idempotent per disk and path).
     */
    public function register(string $disk, string $path, string $collection, string $filename, ?int $width, ?int $height, int $size, ?string $alt = null, ?User $actor = null): Media
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $existing = Media::query()->where('disk', $disk)->where('path', $path)->first();
        if ($existing !== null) {
            return $existing;
        }

        $media = new Media;
        $media->forceFill([
            'disk' => $disk,
            'path' => $path,
            'collection' => $collection,
            'filename' => Str::limit(basename($filename), 180, '') ?: basename($path),
            'mime' => match ($extension) {
                'png' => 'image/png',
                'webp' => 'image/webp',
                default => 'image/jpeg',
            },
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'alt' => $alt !== null ? Str::limit($alt, 180, '') : null,
            'uploaded_by' => $actor?->id,
        ])->save();

        return $media;
    }

    /**
     * Where the image is used.
     *
     * @return array{products: list<array{id: int, name: string}>, themes: bool}
     */
    public function usage(Media $media): array
    {
        $products = Product::query()->withTrashed()
            ->whereHas('images', fn ($q) => $q->where('disk', $media->disk)->where('path', $media->path))
            ->orderBy('name')->get(['id', 'name'])
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => $p->name])->all();

        return ['products' => array_values($products), 'themes' => $this->usedByThemes($media)];
    }

    public function isUsed(Media $media): bool
    {
        $usage = $this->usage($media);

        return $usage['products'] !== [] || $usage['themes'];
    }

    public function updateAlt(Media $media, ?string $alt): void
    {
        $media->forceFill(['alt' => $alt])->save();
    }

    /**
     * @throws ValidationException
     */
    public function delete(Media $media, ?User $actor): void
    {
        if ($this->isUsed($media)) {
            throw ValidationException::withMessages(['media' => 'This image is still used. Remove it from the products or theme settings first.']);
        }

        DB::transaction(function () use ($media, $actor) {
            $media->delete();

            // Theme uploads are also listed in the theme image picker.
            $uploads = array_values(array_filter(
                (array) ($this->settings->get('theme.uploads') ?? []),
                fn ($u) => ! (is_array($u) && ($u['value'] ?? null) === $media->themeValue()),
            ));
            $this->settings->set('theme.uploads', $uploads);

            $this->audit->record('media.deleted', null, ['media' => $media->id, 'file' => $media->filename], $actor);
        });

        Storage::disk($media->disk)->delete($media->path);
    }

    /**
     * Adds library images to a product (the file is shared, not copied).
     *
     * @param  list<int>  $mediaIds
     * @return int number of images added
     */
    public function attachToProduct(Product $product, array $mediaIds, ?User $actor): int
    {
        $media = Media::query()->whereKey($mediaIds)->get();
        $existing = $product->images()->pluck('path')->all();
        $position = (int) $product->images()->max('position');
        $added = 0;

        foreach ($media as $item) {
            if (in_array($item->path, $existing, true)) {
                continue;
            }

            $product->images()->create([
                'disk' => $item->disk,
                'path' => $item->path,
                'alt' => $item->alt ?: $product->name,
                'position' => ++$position,
                'width' => $item->width,
                'height' => $item->height,
            ]);
            $added++;
        }

        if ($added > 0) {
            $this->audit->record('product.images_from_library', $product, ['media' => $media->pluck('id')->all()], $actor);
        }

        return $added;
    }

    /**
     * Called after a product image row is removed: a file uploaded on a
     * product and used nowhere else is deleted with its library entry;
     * library images stay in the library.
     */
    public function releaseProductFile(ProductImage $image): void
    {
        if (ProductImage::query()->where('disk', $image->disk)->where('path', $image->path)->exists()) {
            return;
        }

        $media = Media::query()->where('disk', $image->disk)->where('path', $image->path)->first();

        if ($media !== null && $media->collection !== Media::PRODUCTS) {
            return;
        }

        if ($media !== null && $this->usedByThemes($media)) {
            return;
        }

        $media?->delete();
        Storage::disk($image->disk)->delete($image->path);
    }

    private function usedByThemes(Media $media): bool
    {
        $value = $media->themeValue();

        foreach ($this->settings->all() as $key => $setting) {
            if ($key === 'theme.uploads' || ! str_starts_with($key, 'theme.')) {
                continue;
            }

            if (str_contains((string) json_encode($setting, JSON_UNESCAPED_SLASHES), $value)) {
                return true;
            }
        }

        return false;
    }
}

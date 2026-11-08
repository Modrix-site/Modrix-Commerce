<?php

namespace EcoModular\ImageStudio\Services;

use App\Domains\Catalog\Models\ProductImage;
use EcoModular\ImageStudio\Models\ImageVariant;
use Illuminate\Support\Facades\Storage;

/**
 * Request-scoped lookup of processed variants: every done variant is loaded in
 * one query on first use, later lookups are array reads.
 */
class VariantUrls
{
    /** @var array<int, array{0: string, 1: string}>|null */
    private ?array $map = null;

    public function resolve(ProductImage $image): ?string
    {
        $this->map ??= ImageVariant::query()
            ->where('status', ImageVariant::DONE)
            ->whereNotNull('path')
            ->get(['product_image_id', 'disk', 'path'])
            ->mapWithKeys(fn (ImageVariant $v) => [(int) $v->product_image_id => [(string) $v->disk, (string) $v->path]])
            ->all();

        $entry = $this->map[(int) $image->getKey()] ?? null;

        return $entry === null ? null : Storage::disk($entry[0])->url($entry[1]);
    }
}

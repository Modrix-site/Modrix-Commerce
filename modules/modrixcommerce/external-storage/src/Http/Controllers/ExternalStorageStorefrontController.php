<?php

namespace EcoModular\ExternalStorage\Http\Controllers;

use App\Domains\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use EcoModular\ExternalStorage\Models\ExternalStorageFile;
use EcoModular\ExternalStorage\Support\ExternalStorageSettings;
use Illuminate\Http\JsonResponse;

class ExternalStorageStorefrontController extends Controller
{
    public function productMedia(Product $product, ExternalStorageSettings $settings): JsonResponse
    {
        if (! $settings->enabled()) {
            return response()->json(['videos' => [], 'photos' => []]);
        }

        $files = ExternalStorageFile::query()
            ->where('product_id', $product->id)
            ->where('status', ExternalStorageFile::STATUS_SYNCED)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $videos = $files
            ->filter(fn (ExternalStorageFile $f) => $f->isVideo())
            ->map(fn (ExternalStorageFile $f) => [
                'id' => $f->id,
                'title' => $f->title ?: $f->filename,
                'url' => $f->publicUrl(),
                'mime_type' => $f->mime_type,
                'provider' => $f->provider,
            ])
            ->values()
            ->all();

        $photos = $files
            ->filter(fn (ExternalStorageFile $f) => $f->isPhoto())
            ->map(fn (ExternalStorageFile $f) => [
                'id' => $f->id,
                'title' => $f->title ?: $f->filename,
                'url' => $f->publicUrl(),
                'mime_type' => $f->mime_type,
                'provider' => $f->provider,
            ])
            ->values()
            ->all();

        return response()->json([
            'videos' => $videos,
            'photos' => $photos,
        ]);
    }
}

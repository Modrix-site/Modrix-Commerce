<?php

namespace EcoModular\ImageStudio\Http\Controllers;

use App\Domains\Catalog\Models\ProductImage;
use App\Http\Controllers\Controller;
use EcoModular\ImageStudio\Models\ImageVariant;
use EcoModular\ImageStudio\Services\ImageStudio;
use EcoModular\ImageStudio\Support\ImageStudioSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ImageStudioAdminController extends Controller
{
    private const FILTERS = ['unprocessed', 'pending', 'processing', 'done', 'failed', 'skipped'];

    public function __construct(
        protected ImageStudio $studio,
        protected ImageStudioSettings $settings,
    ) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();
        $status = in_array($status, self::FILTERS, true) ? $status : '';

        $query = ProductImage::query()
            ->with(['product' => fn ($q) => $q->withCurrentTranslation()])
            ->orderByDesc('id');

        if ($status === 'unprocessed') {
            $query->whereNotIn('id', ImageVariant::query()->select('product_image_id'));
        } elseif ($status !== '') {
            $query->whereIn('id', ImageVariant::query()->where('status', $status)->select('product_image_id'));
        }

        $paginated = $query->paginate(24)->withQueryString();

        $variants = ImageVariant::query()
            ->whereIn('product_image_id', $paginated->getCollection()->pluck('id'))
            ->get()
            ->keyBy('product_image_id');

        $images = $paginated->through(function (ProductImage $image) use ($variants) {
            /** @var ImageVariant|null $variant */
            $variant = $variants->get($image->id);

            return [
                'id' => $image->id,
                'productName' => $image->product?->localized('name') ?? '',
                'originalUrl' => Storage::disk($image->disk)->url($image->path),
                'variantUrl' => $variant?->status === ImageVariant::DONE ? $variant->url() : null,
                'status' => $variant->status ?? 'unprocessed',
                'error' => $variant?->error,
                'bytesBefore' => $variant?->bytes_before,
                'bytesAfter' => $variant?->bytes_after,
            ];
        });

        return Inertia::render('module/modrixcommerce/image-studio/admin/index', [
            'stats' => $this->studio->stats(),
            'hasRemoveBgKey' => $this->settings->hasRemoveBgKey(),
            'driver' => $this->settings->driver(),
            'images' => $images,
            'filters' => ['status' => $status],
        ]);
    }

    public function processAll(): RedirectResponse
    {
        $count = 0;

        ProductImage::query()
            ->whereNotIn('id', ImageVariant::query()->select('product_image_id'))
            ->chunkById(200, function ($images) use (&$count): void {
                $count += $this->studio->queueMany($images);
            });

        return back()->with('success', "Queued {$count} ".($count === 1 ? 'image' : 'images').' for processing.');
    }

    public function retry(): RedirectResponse
    {
        $count = 0;

        ImageVariant::query()
            ->whereIn('status', [ImageVariant::FAILED, ImageVariant::SKIPPED])
            ->with('image')
            ->chunkById(200, function ($variants) use (&$count): void {
                $count += $this->studio->queueMany(
                    $variants->map(fn (ImageVariant $v) => $v->image)->filter()
                );
            });

        return back()->with('success', "Re-queued {$count} ".($count === 1 ? 'image' : 'images').'.');
    }

    public function revertAll(): RedirectResponse
    {
        $count = $this->studio->revertAll();

        return back()->with('success', "Reverted {$count} ".($count === 1 ? 'image' : 'images').' to the original.');
    }

    public function process(ProductImage $image): RedirectResponse
    {
        $this->studio->queue($image);

        return back()->with('success', 'Image queued for processing.');
    }

    public function revert(ProductImage $image): RedirectResponse
    {
        $variant = ImageVariant::query()->where('product_image_id', $image->id)->first();

        if ($variant instanceof ImageVariant) {
            $this->studio->revert($variant);

            return back()->with('success', 'Image reverted to the original.');
        }

        return back();
    }

    public function removeBgKey(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'key' => ['nullable', 'string', 'max:200'],
        ]);

        $key = isset($validated['key']) ? trim((string) $validated['key']) : '';
        $this->settings->setRemoveBgKey($key === '' ? null : $key);

        return back()->with('success', $key === '' ? 'remove.bg key cleared.' : 'remove.bg key saved.');
    }
}

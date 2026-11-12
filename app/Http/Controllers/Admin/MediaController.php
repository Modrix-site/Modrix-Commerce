<?php

namespace App\Http\Controllers\Admin;

use App\Core\Media\Media;
use App\Core\Media\MediaLibrary;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Like;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Media library: upload, browse, describe, reuse and delete images.
 */
class MediaController extends Controller
{
    public function __construct(private readonly MediaLibrary $library) {}

    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'collection' => ['nullable', Rule::in([Media::LIBRARY, Media::PRODUCTS, Media::THEMES])],
            'unused' => ['nullable', 'boolean'],
        ]);

        $query = Media::query()
            ->when($filters['search'] ?? null, fn ($q, $s) => $q->where(fn ($w) => Like::orWhere(Like::where($w, 'filename', $s), 'alt', $s)))
            ->when($filters['collection'] ?? null, fn ($q, $c) => $q->where('collection', $c))
            ->when($filters['unused'] ?? false, fn ($q) => $q->whereNotExists(fn ($sub) => $sub->selectRaw('1')->from('product_images')
                ->whereColumn('product_images.path', 'media.path')->whereColumn('product_images.disk', 'media.disk')))
            ->latest('id');

        $productCounts = ProductImage::query()->selectRaw('path, count(distinct product_id) as products')->groupBy('path')->pluck('products', 'path');

        return Inertia::render('admin/media/index', [
            'media' => $query->paginate(48)->withQueryString()->through(fn (Media $m) => $this->present($m, (int) ($productCounts[$m->path] ?? 0))),
            'filters' => [
                'search' => $filters['search'] ?? '',
                'collection' => $filters['collection'] ?? '',
                'unused' => (bool) ($filters['unused'] ?? false),
            ],
            'maxUploadMb' => 10,
        ]);
    }

    /**
     * Library picker data (JSON) for the product form.
     */
    public function picker(Request $request): JsonResponse
    {
        $search = (string) $request->string('search');

        return response()->json([
            'media' => Media::query()
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => Like::orWhere(Like::where($w, 'filename', $search), 'alt', $search)))
                ->latest('id')->limit(60)->get()
                ->map(fn (Media $m) => ['id' => $m->id, 'url' => $m->url(), 'filename' => $m->filename, 'alt' => $m->alt]),
        ]);
    }

    public function show(Media $media): JsonResponse
    {
        return response()->json([...$this->present($media, 0), 'usage' => $this->library->usage($media)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:20'],
            'files.*' => ['file', 'max:10240'],
            'alt' => ['nullable', 'string', 'max:180'],
        ]);

        $count = 0;
        foreach ((array) $request->file('files') as $file) {
            if ($file instanceof UploadedFile) {
                $this->library->upload($file, $request->string('alt')->toString() ?: null, $this->actor($request));
                $count++;
            }
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $count === 1 ? 'Image uploaded.' : "{$count} images uploaded."]);

        return back();
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $data = $request->validate(['alt' => ['nullable', 'string', 'max:180']]);
        $this->library->updateAlt($media, $data['alt'] ?? null);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Description saved.']);

        return back();
    }

    public function destroy(Request $request, Media $media): RedirectResponse
    {
        $this->library->delete($media, $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Image deleted.']);

        return back();
    }

    public function attach(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'media' => ['required', 'array', 'min:1', 'max:20'],
            'media.*' => ['integer', 'exists:media,id'],
        ]);

        $added = $this->library->attachToProduct($product, array_values(array_map('intval', $data['media'])), $this->actor($request));
        Inertia::flash('toast', ['type' => 'success', 'message' => $added === 0 ? 'Those images are already on this product.' : "{$added} image(s) added from the library."]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Media $m, int $products): array
    {
        return [
            'id' => $m->id,
            'url' => $m->url(),
            'filename' => $m->filename,
            'alt' => $m->alt,
            'collection' => $m->collection,
            'mime' => $m->mime,
            'size' => $m->size ?: (int) (Storage::disk($m->disk)->exists($m->path) ? Storage::disk($m->disk)->size($m->path) : 0),
            'width' => $m->width,
            'height' => $m->height,
            'products' => $products,
            'created_at' => $m->created_at?->toDateString(),
        ];
    }

    private function actor(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}

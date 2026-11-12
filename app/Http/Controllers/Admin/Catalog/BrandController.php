<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Core\Audit\AuditLogger;
use App\Core\Media\Media;
use App\Domains\Catalog\Models\Brand;
use App\Domains\Catalog\Slugger;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin → Catalog → Brands. Deleting a brand keeps its products (without a brand).
 */
class BrandController extends Controller
{
    private const SLUG_LENGTH = 130;

    public function index(): Response
    {
        $current = Brand::query()->whereNotNull('logo_media_id')->pluck('logo_media_id')->all();

        return Inertia::render('admin/brands/index', [
            'brands' => Brand::query()->with('logo')->withCount('products')->orderBy('name')->get()->map(fn (Brand $b) => [
                'id' => $b->id,
                'name' => $b->name,
                'slug' => $b->slug,
                'description' => $b->description,
                'logo_media_id' => $b->logo_media_id,
                'logo_url' => $b->logo?->url(),
                'products_count' => $b->products_count,
                'url' => route('storefront.brand', $b->slug),
            ])->all(),
            // The newest images, plus every logo already in use so editing never drops it.
            'logos' => Media::query()->where('mime', 'like', 'image/%')
                ->where(fn ($q) => $q->whereIn('id', Media::query()->where('mime', 'like', 'image/%')->latest('id')->limit(100)->pluck('id'))->orWhereIn('id', $current))
                ->latest('id')->get()
                ->map(fn (Media $m) => ['id' => $m->id, 'filename' => $m->filename])->values(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, null);
        $brand = Brand::query()->create([...$data, 'slug' => filled($data['slug'] ?? null) ? $data['slug'] : $this->slugFor($data['name'])]);
        $audit->record('brand.created', $brand, ['name' => $brand->name]);

        return $this->done(__('Brand created.'));
    }

    public function update(Request $request, Brand $brand, AuditLogger $audit): RedirectResponse
    {
        $data = $this->validated($request, $brand);
        $brand->update([...$data, 'slug' => filled($data['slug'] ?? null) ? $data['slug'] : $brand->slug]);
        $audit->record('brand.updated', $brand, ['name' => $brand->name]);

        return $this->done(__('Brand saved.'));
    }

    public function destroy(Brand $brand, AuditLogger $audit): RedirectResponse
    {
        $audit->record('brand.deleted', $brand, ['name' => $brand->name, 'products' => $brand->products()->count()]);
        $brand->delete();

        return $this->done(__('Brand deleted. Its products are kept without a brand.'));
    }

    /**
     * @return array{name: string, slug?: string|null, description?: string|null, logo_media_id?: int|null}
     */
    private function validated(Request $request, ?Brand $brand): array
    {
        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        }

        /** @var array{name: string, slug?: string|null, description?: string|null, logo_media_id?: int|null} */
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('brands', 'slug')->ignore($brand?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo_media_id' => ['nullable', 'integer', Rule::exists('media', 'id')->where(fn ($q) => $q->where('mime', 'like', 'image/%'))],
        ]);
    }

    /** A unique slug that fits the column, even for long non-Latin names. */
    private function slugFor(string $name): string
    {
        $slug = Slugger::unique(Brand::class, $name);

        return strlen($slug) <= self::SLUG_LENGTH ? $slug : Slugger::unique(Brand::class, Str::limit(Str::slug($name), self::SLUG_LENGTH - 10, '') ?: 'brand');
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return redirect()->route('admin.brands.index');
    }
}

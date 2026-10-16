<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Core\Audit\AuditLogger;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductFile;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The files a digital product delivers: upload, rename, reorder, delete.
 */
class ProductFileController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function store(Request $request, Product $product): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:'.ProductFile::MAX_KB]]);

        if ($product->files()->count() >= ProductFile::MAX_PER_PRODUCT) {
            throw ValidationException::withMessages(['file' => __('A product can have at most :max files.', ['max' => ProductFile::MAX_PER_PRODUCT])]);
        }

        $upload = $request->file('file');
        abort_unless($upload !== null && ! is_array($upload), 422);
        $path = $upload->store("downloads/{$product->id}", ProductFile::DISK);
        abort_if($path === false, 500, 'The file could not be stored.');

        $file = $product->files()->create([
            'name' => mb_substr(str_replace(['/', '\\'], '-', $upload->getClientOriginalName()), 0, 160),
            'disk' => ProductFile::DISK,
            'path' => $path,
            'size' => (int) $upload->getSize(),
            'mime' => $upload->getMimeType(),
            'position' => (int) $product->files()->max('position') + 1,
        ]);
        $this->audit->record('product.file_added', $product, ['file_id' => $file->id, 'name' => $file->name]);

        return $this->done(__('File uploaded.'));
    }

    public function update(Request $request, Product $product, ProductFile $file): RedirectResponse
    {
        $data = $request->validate([
            // Slashes would break the downloaded file's name.
            'name' => ['sometimes', 'required', 'string', 'max:160', 'not_regex:#[/\\\\]#'],
            'position' => ['sometimes', 'integer', 'min:0', 'max:'.ProductFile::MAX_PER_PRODUCT],
        ]);

        DB::transaction(function () use ($product, $file, $data) {
            if (isset($data['name'])) {
                $file->update(['name' => $data['name']]);
            }

            if (isset($data['position'])) {
                // Move the file to the new place and renumber the rest.
                $ids = $product->files()->pluck('id')->reject(fn ($id) => $id === $file->id)->values()->all();
                array_splice($ids, min((int) $data['position'], count($ids)), 0, [$file->id]);

                foreach ($ids as $position => $id) {
                    ProductFile::query()->whereKey($id)->update(['position' => $position]);
                }
            }
        });

        return $this->done(__('File updated.'));
    }

    public function destroy(Product $product, ProductFile $file): RedirectResponse
    {
        $file->delete();
        $this->audit->record('product.file_removed', $product, ['file_id' => $file->id, 'name' => $file->name]);

        return $this->done(__('File deleted.'));
    }

    private function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

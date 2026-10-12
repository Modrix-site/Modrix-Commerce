<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Domains\Catalog\Actions\ManageProductImages;
use App\Domains\Catalog\Actions\StoreProductImage;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductImageController extends Controller
{
    public function store(Request $request, Product $product, StoreProductImage $store): RedirectResponse
    {
        $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ]);

        foreach ($request->file('images', []) as $file) {
            $store->handle($product, $file);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Images uploaded.')]);

        return redirect()->route('admin.products.edit', $product);
    }

    public function reorder(Request $request, Product $product, ManageProductImages $images): RedirectResponse
    {
        $data = $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer']]);
        $images->reorder($product, array_values(array_map('intval', $data['order'])));

        return redirect()->route('admin.products.edit', $product);
    }

    public function update(Request $request, Product $product, ProductImage $image, ManageProductImages $images): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $data = $request->validate(['alt' => ['nullable', 'string', 'max:191']]);
        $images->updateAlt($image, $data['alt'] ?? null);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image updated.')]);

        return redirect()->route('admin.products.edit', $product);
    }

    public function destroy(Product $product, ProductImage $image, ManageProductImages $images): RedirectResponse
    {
        abort_unless($image->product_id === $product->id, 404);
        $images->delete($image);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Image removed.')]);

        return redirect()->route('admin.products.edit', $product);
    }
}

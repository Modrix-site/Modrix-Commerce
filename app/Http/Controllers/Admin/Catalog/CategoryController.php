<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Domains\Catalog\Actions\DeleteCategory;
use App\Domains\Catalog\Actions\SaveCategory;
use App\Domains\Catalog\CategoryTree;
use App\Domains\Catalog\Models\Category;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $counts = DB::table('product_categories')
            ->select('category_id', DB::raw('count(*) as aggregate'))
            ->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        $tree = array_map(fn (array $c) => $c + ['products_count' => (int) ($counts[$c['id']] ?? 0)], CategoryTree::flatten());

        return Inertia::render('admin/categories/index', ['categories' => $tree]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/categories/form', [
            'category' => null,
            'parents' => CategoryTree::flatten(),
        ]);
    }

    public function store(Request $request, SaveCategory $save): RedirectResponse
    {
        $save->handle(null, $this->validated($request, null));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category created.')]);

        return redirect()->route('admin.categories.index');
    }

    public function edit(Category $category): Response
    {
        $excluded = $category->descendantIdsAndSelf();

        return Inertia::render('admin/categories/form', [
            'category' => $category->only(['id', 'parent_id', 'name', 'slug', 'description', 'position', 'is_active', 'meta_title', 'meta_description']),
            'parents' => array_values(array_filter(CategoryTree::flatten(), fn ($c) => ! in_array($c['id'], $excluded, true))),
        ]);
    }

    public function update(Request $request, Category $category, SaveCategory $save): RedirectResponse
    {
        $save->handle($category, $this->validated($request, $category));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category saved.')]);

        return redirect()->route('admin.categories.index');
    }

    public function destroy(Category $category, DeleteCategory $delete): RedirectResponse
    {
        $delete->handle($category);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Category deleted.')]);

        return redirect()->route('admin.categories.index');
    }

    /**
     * @return array{name: string, slug?: string|null, parent_id?: int|null, description?: string|null, position?: int|null, is_active?: bool, meta_title?: string|null, meta_description?: string|null}
     */
    private function validated(Request $request, ?Category $category): array
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);

        /** @var array{name: string, slug?: string|null, parent_id?: int|null, description?: string|null, position?: int|null, is_active?: bool, meta_title?: string|null, meta_description?: string|null} */
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string', 'max:5000'],
            'position' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'meta_title' => ['nullable', 'string', 'max:191'],
            'meta_description' => ['nullable', 'string', 'max:500'],
        ]);
    }
}

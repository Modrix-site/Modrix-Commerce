<?php

namespace Marketplace\Http\Controllers\Admin;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Marketplace\Models\Category;

/** Catalog categories: the slug is fixed once created so links keep working. */
class CategoryController
{
    public function index(): View
    {
        return view('admin.categories', ['categories' => Category::query()->withCount('packages')->orderBy('position')->orderBy('name')->get()]);
    }

    public function save(Request $request): RedirectResponse
    {
        $category = $request->filled('id') ? Category::query()->findOrFail((int) $request->input('id')) : null;
        $data = $request->validate([
            'slug' => $category ? ['nullable'] : ['required', 'string', 'max:64', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::unique('categories', 'slug')],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'in:all,theme,module'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $values = ['name' => $data['name'], 'description' => (string) ($data['description'] ?? ''), 'type' => $data['type'], 'position' => (int) ($data['position'] ?? 0), 'enabled' => $request->boolean('enabled')];

        if ($category) {
            $category->update($values);
        } else {
            Category::query()->create(['slug' => $data['slug'], ...$values]);
        }

        return redirect('/admin/categories')->with('notice', 'Category saved.');
    }
}

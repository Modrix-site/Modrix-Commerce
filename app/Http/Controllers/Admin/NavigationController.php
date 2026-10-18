<?php

namespace App\Http\Controllers\Admin;

use App\Core\Audit\AuditLogger;
use App\Core\Themes\StoreNavigation;
use App\Core\Themes\ThemeResolver;
use App\Core\Themes\ThemeSchema;
use App\Domains\Catalog\Models\Category;
use App\Domains\Content\Models\Page;
use App\Http\Controllers\Controller;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class NavigationController extends Controller
{
    public function index(Request $request, StoreNavigation $menus, ThemeResolver $themes): Response
    {
        return Inertia::render('admin/navigation/index', ['menus' => $menus->forEditing($themes->forRequest($request)), 'pages' => Page::orderBy('title')->get(['id', 'title', 'status']), 'categories' => Category::orderBy('name')->get(['id', 'name', 'parent_id', 'is_active'])]);
    }

    public function update(Request $request, StoreNavigation $menus, AuditLogger $audit): RedirectResponse
    {
        $safeLink = function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || ! ThemeSchema::isSafeLink($value) || $value === '') {
                $fail('Use a relative path starting with / or a secure https:// address.');
            }
        };
        $data = $request->validate([
            'header' => ['required', 'array:title,links'], 'header.title' => ['required', 'string', 'max:100'],
            'header.links' => ['present', 'array', 'list', 'max:30'], 'header.links.*' => ['array:label,url,type,page_id,category_id'],
            'header.links.*.label' => ['required', 'string', 'max:100'], 'header.links.*.url' => ['required_if:header.links.*.type,link', 'required_without:header.links.*.type', 'nullable', 'string', 'max:500', $safeLink],
            'header.links.*.type' => ['sometimes', 'required', Rule::in(['link', 'page', 'category'])],
            'header.links.*.page_id' => ['required_if:header.links.*.type,page', 'nullable', 'integer', Rule::exists('pages', 'id')->whereNull('deleted_at')],
            'header.links.*.category_id' => ['required_if:header.links.*.type,category', 'nullable', 'integer', 'exists:categories,id'],
            'footer' => ['required', 'array', 'list', 'size:3'], 'footer.*' => ['array:title,links'], 'footer.*.title' => ['required', 'string', 'max:100'],
            'footer.*.links' => ['present', 'array', 'list', 'max:20'], 'footer.*.links.*' => ['array:label,url,type,page_id,category_id'],
            'footer.*.links.*.label' => ['required', 'string', 'max:100'], 'footer.*.links.*.url' => ['required_if:footer.*.links.*.type,link', 'required_without:footer.*.links.*.type', 'nullable', 'string', 'max:500', $safeLink],
            'footer.*.links.*.type' => ['sometimes', 'required', Rule::in(['link', 'page', 'category'])],
            'footer.*.links.*.page_id' => ['required_if:footer.*.links.*.type,page', 'nullable', 'integer', Rule::exists('pages', 'id')->whereNull('deleted_at')],
            'footer.*.links.*.category_id' => ['required_if:footer.*.links.*.type,category', 'nullable', 'integer', 'exists:categories,id'],
        ]);
        $menus->save($data);
        $audit->record('navigation.updated');
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Navigation saved. Header and footer menus now apply across themes.']);

        return back();
    }
}
